/**
 * Offline Planning Manager
 * Beheert offline functionaliteit voor planning taken en foto's
 */
class OfflinePlanningManager {
    constructor() {
        this.dbName = 'PlanningOfflineDB';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.syncListeners = [];
        
        this.initDatabase();
        this.initEventListeners();
    }
    
    async initDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Planning data store
                if (!db.objectStoreNames.contains('plannings')) {
                    const planningStore = db.createObjectStore('plannings', { keyPath: 'id' });
                    planningStore.createIndex('planned_date', 'planned_date');
                    planningStore.createIndex('sync_status', 'sync_status');
                }
                
                // Task completions store
                if (!db.objectStoreNames.contains('task_completions')) {
                    const completionsStore = db.createObjectStore('task_completions', { keyPath: 'sync_hash' });
                    completionsStore.createIndex('planning_task_id', 'planning_task_id');
                    completionsStore.createIndex('sync_status', 'sync_status');
                }
                
                // Photos store
                if (!db.objectStoreNames.contains('photos')) {
                    const photosStore = db.createObjectStore('photos', { keyPath: 'sync_hash' });
                    photosStore.createIndex('completion_sync_hash', 'completion_sync_hash');
                    photosStore.createIndex('sync_status', 'sync_status');
                }
            };
        });
    }
    
    initEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.attemptSync();
            this.notifyListeners('online');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.notifyListeners('offline');
        });
    }

    onSyncStatusChange(callback) {
        this.syncListeners.push(callback);
    }

    notifyListeners(event, data = {}) {
        this.syncListeners.forEach(callback => {
            try {
                callback({ event, data, isOnline: this.isOnline, syncInProgress: this.syncInProgress });
            } catch (error) {
                console.error('Error in sync listener:', error);
            }
        });
    }
    
    async cachePlanningData(planningId) {
        try {
            const response = await fetch(`/api/v1/offline/planning/${planningId}/full`, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch planning data');
            }
            
            const data = await response.json();
            
            // Store in IndexedDB
            const transaction = this.db.transaction(['plannings'], 'readwrite');
            const store = transaction.objectStore('plannings');
            
            await store.put({
                ...data.planning,
                offline_data: data.offline_data,
                cached_at: new Date().toISOString()
            });
            
            console.log('Planning data cached successfully');
            return data;
        } catch (error) {
            console.error('Error caching planning data:', error);
            throw error;
        }
    }

    async getCachedPlanningData(planningId) {
        try {
            const transaction = this.db.transaction(['plannings'], 'readonly');
            const store = transaction.objectStore('plannings');
            const data = await this.promisifyRequest(store.get(planningId));
            return data;
        } catch (error) {
            console.error('Error getting cached planning data:', error);
            return null;
        }
    }
    
    async saveTaskCompletion(planningTaskId, completionData, photos = []) {
        const syncHash = this.generateSyncHash();
        
        const completion = {
            sync_hash: syncHash,
            planning_task_id: planningTaskId,
            comment: completionData.comment,
            is_fully_completed: completionData.is_fully_completed,
            completed_offline_at: new Date().toISOString(),
            task_duration_seconds: completionData.task_duration_seconds || 0,
            sync_status: 'pending',
            photos: []
        };
        
        // Process photos
        for (let i = 0; i < photos.length; i++) {
            const photo = photos[i];
            const photoSyncHash = this.generateSyncHash();
            
            // Convert to base64
            const fileData = await this.fileToBase64(photo);
            
            const photoData = {
                sync_hash: photoSyncHash,
                completion_sync_hash: syncHash,
                file_data: fileData.split(',')[1], // Remove data:image/jpeg;base64, prefix
                filename: photo.name,
                taken_at: new Date().toISOString(),
                sync_status: 'pending'
            };
            
            // Store photo in IndexedDB
            const photoTransaction = this.db.transaction(['photos'], 'readwrite');
            await this.promisifyRequest(photoTransaction.objectStore('photos').put(photoData));
            
            completion.photos.push(photoSyncHash);
        }
        
        // Store completion in IndexedDB
        const transaction = this.db.transaction(['task_completions'], 'readwrite');
        await this.promisifyRequest(transaction.objectStore('task_completions').put(completion));
        
        console.log('Task completion saved offline:', syncHash);
        
        // Attempt immediate sync if online
        if (this.isOnline) {
            this.attemptSync();
        }
        
        return syncHash;
    }
    
    async attemptSync() {
        if (this.syncInProgress || !this.isOnline) {
            return;
        }
        
        this.syncInProgress = true;
        this.notifyListeners('sync_started');
        
        try {
            await this.syncCompletions();
            await this.syncPhotos();
            console.log('Sync completed successfully');
            this.notifyListeners('sync_completed');
        } catch (error) {
            console.error('Sync failed:', error);
            this.notifyListeners('sync_failed', { error: error.message });
        } finally {
            this.syncInProgress = false;
        }
    }
    
    async syncCompletions() {
        const transaction = this.db.transaction(['task_completions'], 'readonly');
        const store = transaction.objectStore('task_completions');
        const index = store.index('sync_status');
        
        const pendingCompletions = await this.getAllFromIndex(index, 'pending');
        
        if (pendingCompletions.length === 0) {
            return;
        }
        
        console.log(`Syncing ${pendingCompletions.length} completions`);
        
        try {
            const response = await fetch('/api/v1/offline/sync/planning-tasks', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    completions: pendingCompletions
                })
            });
            
            if (!response.ok) {
                throw new Error('Completion sync failed');
            }
            
            const results = await response.json();
            
            // Update sync status
            const updateTransaction = this.db.transaction(['task_completions'], 'readwrite');
            const updateStore = updateTransaction.objectStore('task_completions');
            
            for (const result of results.results) {
                if (result.status === 'synced' || result.status === 'already_synced') {
                    const completion = await this.promisifyRequest(updateStore.get(result.sync_hash));
                    if (completion) {
                        completion.sync_status = 'synced';
                        completion.synced_at = new Date().toISOString();
                        await this.promisifyRequest(updateStore.put(completion));
                    }
                }
            }
        } catch (error) {
            console.error('Error syncing completions:', error);
            throw error;
        }
    }
    
    async syncPhotos() {
        const transaction = this.db.transaction(['photos'], 'readonly');
        const store = transaction.objectStore('photos');
        const index = store.index('sync_status');
        
        const pendingPhotos = await this.getAllFromIndex(index, 'pending');
        
        if (pendingPhotos.length === 0) {
            return;
        }
        
        console.log(`Syncing ${pendingPhotos.length} photos`);
        
        try {
            const response = await fetch('/api/v1/offline/sync/photos', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken()
                },
                body: JSON.stringify({
                    photos: pendingPhotos
                })
            });
            
            if (!response.ok) {
                throw new Error('Photo sync failed');
            }
            
            const results = await response.json();
            
            // Update sync status
            const updateTransaction = this.db.transaction(['photos'], 'readwrite');
            const updateStore = updateTransaction.objectStore('photos');
            
            for (const result of results.results) {
                if (result.status === 'synced' || result.status === 'already_synced') {
                    const photo = await this.promisifyRequest(updateStore.get(result.sync_hash));
                    if (photo) {
                        photo.sync_status = 'synced';
                        photo.synced_at = new Date().toISOString();
                        await this.promisifyRequest(updateStore.put(photo));
                    }
                }
            }
        } catch (error) {
            console.error('Error syncing photos:', error);
            throw error;
        }
    }
    
    async getPendingSyncCount() {
        const completionTransaction = this.db.transaction(['task_completions'], 'readonly');
        const completionIndex = completionTransaction.objectStore('task_completions').index('sync_status');
        const pendingCompletions = await this.getAllFromIndex(completionIndex, 'pending');
        
        const photoTransaction = this.db.transaction(['photos'], 'readonly');
        const photoIndex = photoTransaction.objectStore('photos').index('sync_status');
        const pendingPhotos = await this.getAllFromIndex(photoIndex, 'pending');
        
        return {
            completions: pendingCompletions.length,
            photos: pendingPhotos.length,
            total: pendingCompletions.length + pendingPhotos.length
        };
    }

    async getAllOfflineCompletions(planningTaskId = null) {
        const transaction = this.db.transaction(['task_completions'], 'readonly');
        const store = transaction.objectStore('task_completions');
        
        if (planningTaskId) {
            const index = store.index('planning_task_id');
            return await this.getAllFromIndex(index, planningTaskId);
        } else {
            return await this.promisifyRequest(store.getAll());
        }
    }
    
    // Helper methods
    generateSyncHash() {
        return 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    async fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    }
    
    async getAllFromIndex(index, value) {
        return new Promise((resolve, reject) => {
            const request = index.getAll(value);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    promisifyRequest(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    getAuthToken() {
        // Get Sanctum token from meta tag or localStorage
        const token = document.querySelector('meta[name="auth-token"]')?.getAttribute('content');
        return token || localStorage.getItem('auth_token') || '';
    }

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
}

// Initialize global instance
if (typeof window !== 'undefined') {
    window.offlinePlanningManager = new OfflinePlanningManager();
}

export default OfflinePlanningManager; 