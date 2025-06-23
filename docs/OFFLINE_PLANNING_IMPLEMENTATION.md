# Offline Planning Implementatie

## Overzicht

De myplanning functionaliteiten moeten offline beschikbaar zijn voor tablet gebruikers die in gebouwen zonder internet werken. Deze implementatie zorgt ervoor dat gebruikers:

1. Planningsdata kunnen laden en offline bewaren
2. Taken kunnen uitvoeren zonder internetverbinding
3. Foto's kunnen toevoegen die later gesynchroniseerd worden
4. Automatische synchronisatie hebben zodra internet weer beschikbaar is

## Technische Architectuur

### Frontend Offline Storage

#### Service Worker Implementatie
```javascript
// public/sw.js - Service Worker voor offline functionaliteit
const CACHE_NAME = 'planning-cache-v1';
const STATIC_CACHE = 'static-cache-v1';
const API_CACHE = 'api-cache-v1';

const STATIC_ASSETS = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/offline',
    '/manifest.json'
];

const API_ENDPOINTS = [
    '/api/v1/my-planning/',
    '/api/v1/planning-tasks/',
    '/api/v1/locations/'
];
```

#### IndexedDB Schema
```javascript
// Database structure voor offline opslag
const DB_SCHEMA = {
    plannings: {
        keyPath: 'id',
        indexes: ['planned_date', 'user_id', 'sync_status']
    },
    planning_tasks: {
        keyPath: 'id',
        indexes: ['planning_id', 'status', 'sync_status']
    },
    task_completions: {
        keyPath: 'id',
        indexes: ['planning_task_id', 'user_id', 'sync_status']
    },
    photos: {
        keyPath: 'id',
        indexes: ['completion_id', 'sync_status', 'file_hash']
    },
    sync_queue: {
        keyPath: 'id',
        indexes: ['type', 'priority', 'created_at']
    }
};
```

### Backend API Wijzigingen

#### Nieuwe Offline API Endpoints
```php
// routes/api.php
Route::prefix('v1/offline')->middleware('auth:sanctum')->group(function () {
    Route::get('/planning/{planning}/full', [OfflinePlanningController::class, 'getFullPlanningData']);
    Route::post('/sync/planning-tasks', [OfflineSyncController::class, 'syncPlanningTasks']);
    Route::post('/sync/photos', [OfflineSyncController::class, 'syncPhotos']);
    Route::get('/sync/status', [OfflineSyncController::class, 'getSyncStatus']);
});
```

#### Database Wijzigingen
```sql
-- Offline sync tracking
ALTER TABLE planning_tasks ADD COLUMN sync_hash VARCHAR(255) NULL;
ALTER TABLE planning_task_completions ADD COLUMN sync_hash VARCHAR(255) NULL;
ALTER TABLE planning_task_completion_photos ADD COLUMN sync_hash VARCHAR(255) NULL;

-- Offline sync queue table
CREATE TABLE offline_sync_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL, -- 'create', 'update', 'delete'
    payload JSON NOT NULL,
    sync_hash VARCHAR(255) NOT NULL UNIQUE,
    priority INT DEFAULT 10,
    attempts INT DEFAULT 0,
    last_attempt_at TIMESTAMP NULL,
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_synced (user_id, synced_at),
    INDEX idx_sync_hash (sync_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Implementatie Details

### 1. Offline Planning Controller

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Planning;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OfflinePlanningController extends Controller
{
    public function getFullPlanningData(Planning $planning): JsonResponse
    {
        $user = Auth::user();
        
        // Controleer toegang
        if (!$user->isAdmin() && !$planning->users->contains($user)) {
            abort(403, 'Geen toegang tot deze planning.');
        }
        
        // Laad alle benodigde data voor offline gebruik
        $planning->load([
            'locations',
            'users',
            'planningTasks.task.location',
            'planningTasks.task.taskPhotos',
            'planningTasks.task.benodigdheden',
            'planningTasks.defaultTask.benodigdheden',
            'planningTasks.specificLocation',
            'planningTasks.completions.photos',
            'planningTasks.completions.user',
            'endChecklistItems.benodigdheid'
        ]);
        
        return response()->json([
            'planning' => $planning,
            'offline_data' => [
                'last_sync' => now()->toISOString(),
                'sync_hash' => $this->generateSyncHash($planning),
                'expires_at' => now()->addHours(24)->toISOString()
            ]
        ]);
    }
    
    private function generateSyncHash(Planning $planning): string
    {
        $data = [
            'planning_updated_at' => $planning->updated_at->timestamp,
            'tasks_count' => $planning->planningTasks->count(),
            'completions_count' => $planning->planningTasks->sum(fn($pt) => $pt->completions->count())
        ];
        
        return hash('sha256', json_encode($data));
    }
}
```

### 2. Offline Sync Controller

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfflineSyncQueue;
use App\Models\PlanningTask;
use App\Models\PlanningTaskCompletion;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncController extends Controller
{
    public function syncPlanningTasks(Request $request): JsonResponse
    {
        $request->validate([
            'completions' => 'required|array',
            'completions.*.planning_task_id' => 'required|exists:planning_tasks,id',
            'completions.*.sync_hash' => 'required|string',
            'completions.*.comment' => 'required|string',
            'completions.*.is_fully_completed' => 'required|boolean',
            'completions.*.completed_offline_at' => 'required|date',
            'completions.*.photos' => 'sometimes|array'
        ]);
        
        $results = [];
        $user = Auth::user();
        
        foreach ($request->input('completions') as $completionData) {
            try {
                $results[] = $this->syncSingleCompletion($completionData, $user);
            } catch (\Exception $e) {
                Log::error('Sync error for completion: ' . $e->getMessage(), $completionData);
                $results[] = [
                    'sync_hash' => $completionData['sync_hash'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return response()->json(['results' => $results]);
    }
    
    private function syncSingleCompletion(array $data, $user): array
    {
        // Controleer of deze completion al eerder is gesynchroniseerd
        if (PlanningTaskCompletion::where('sync_hash', $data['sync_hash'])->exists()) {
            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'already_synced'
            ];
        }
        
        return DB::transaction(function () use ($data, $user) {
            $planningTask = PlanningTask::findOrFail($data['planning_task_id']);
            
            $completion = $planningTask->completions()->create([
                'user_id' => $user->id,
                'comment' => $data['comment'],
                'is_fully_completed' => $data['is_fully_completed'],
                'sync_hash' => $data['sync_hash'],
                'created_at' => $data['completed_offline_at']
            ]);
            
            // Update planning task status
            $planningTask->update([
                'completed_at' => $data['completed_offline_at'],
                'completed_notes' => $data['comment'],
                'status' => $user->isAdmin() ? 'completed' : 'review'
            ]);
            
            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'synced',
                'completion_id' => $completion->id
            ];
        });
    }
    
    public function syncPhotos(Request $request, ImageService $imageService): JsonResponse
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*.completion_sync_hash' => 'required|string',
            'photos.*.sync_hash' => 'required|string',
            'photos.*.file_data' => 'required|string', // Base64 encoded
            'photos.*.filename' => 'required|string',
            'photos.*.taken_at' => 'required|date'
        ]);
        
        $results = [];
        
        foreach ($request->input('photos') as $photoData) {
            try {
                $results[] = $this->syncSinglePhoto($photoData, $imageService);
            } catch (\Exception $e) {
                Log::error('Photo sync error: ' . $e->getMessage(), $photoData);
                $results[] = [
                    'sync_hash' => $photoData['sync_hash'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return response()->json(['results' => $results]);
    }
    
    private function syncSinglePhoto(array $data, ImageService $imageService): array
    {
        // Vind de completion
        $completion = PlanningTaskCompletion::where('sync_hash', $data['completion_sync_hash'])->first();
        
        if (!$completion) {
            throw new \Exception('Completion not found for sync_hash: ' . $data['completion_sync_hash']);
        }
        
        // Controleer of foto al bestaat
        if ($completion->photos()->where('sync_hash', $data['sync_hash'])->exists()) {
            return [
                'sync_hash' => $data['sync_hash'],
                'status' => 'already_synced'
            ];
        }
        
        // Decode en sla foto op
        $fileData = base64_decode($data['file_data']);
        $tempFile = tmpfile();
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        file_put_contents($tempPath, $fileData);
        
        $extension = pathinfo($data['filename'], PATHINFO_EXTENSION);
        $filename = uniqid('ptc_' . $completion->id . '_', true) . '.' . $extension;
        
        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempPath,
            $data['filename'],
            mime_content_type($tempPath),
            null,
            true
        );
        
        $path = $imageService->saveCompressedImage(
            $uploadedFile,
            'planning-task-completion-photos/' . $completion->id,
            $filename,
            'public'
        );
        
        $photo = $completion->photos()->create([
            'file_path' => $path,
            'sync_hash' => $data['sync_hash'],
            'created_at' => $data['taken_at']
        ]);
        
        fclose($tempFile);
        
        return [
            'sync_hash' => $data['sync_hash'],
            'status' => 'synced',
            'photo_id' => $photo->id
        ];
    }
}
```

### 3. Frontend Offline Manager

```javascript
// resources/js/offline-manager.js
class OfflinePlanningManager {
    constructor() {
        this.dbName = 'PlanningOfflineDB';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        
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
                const planningStore = db.createObjectStore('plannings', { keyPath: 'id' });
                planningStore.createIndex('planned_date', 'planned_date');
                planningStore.createIndex('sync_status', 'sync_status');
                
                // Task completions store
                const completionsStore = db.createObjectStore('task_completions', { keyPath: 'sync_hash' });
                completionsStore.createIndex('planning_task_id', 'planning_task_id');
                completionsStore.createIndex('sync_status', 'sync_status');
                
                // Photos store
                const photosStore = db.createObjectStore('photos', { keyPath: 'sync_hash' });
                photosStore.createIndex('completion_sync_hash', 'completion_sync_hash');
                photosStore.createIndex('sync_status', 'sync_status');
            };
        });
    }
    
    initEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.attemptSync();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
        });
    }
    
    async cachePlanningData(planningId) {
        try {
            const response = await fetch(`/api/v1/offline/planning/${planningId}/full`, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json'
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
            
            return data;
        } catch (error) {
            console.error('Error caching planning data:', error);
            throw error;
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
            await photoTransaction.objectStore('photos').put(photoData);
            
            completion.photos.push(photoSyncHash);
        }
        
        // Store completion in IndexedDB
        const transaction = this.db.transaction(['task_completions'], 'readwrite');
        await transaction.objectStore('task_completions').put(completion);
        
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
        
        try {
            await this.syncCompletions();
            await this.syncPhotos();
        } catch (error) {
            console.error('Sync failed:', error);
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
        
        try {
            const response = await fetch('/api/v1/offline/sync/planning-tasks', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
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
                    const completion = await updateStore.get(result.sync_hash);
                    if (completion) {
                        completion.sync_status = 'synced';
                        completion.synced_at = new Date().toISOString();
                        await updateStore.put(completion);
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
        
        try {
            const response = await fetch('/api/v1/offline/sync/photos', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
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
                    const photo = await updateStore.get(result.sync_hash);
                    if (photo) {
                        photo.sync_status = 'synced';
                        photo.synced_at = new Date().toISOString();
                        await updateStore.put(photo);
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
    
    getAuthToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
}

// Globale instance
window.offlinePlanningManager = new OfflinePlanningManager();
```

### 4. Service Worker Implementatie

```javascript
// public/sw.js
const CACHE_NAME = 'planning-offline-v1';
const STATIC_CACHE = 'static-offline-v1';

const STATIC_ASSETS = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/offline.html',
    '/manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)),
            caches.open(CACHE_NAME)
        ])
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Cache planning pages
    if (url.pathname.startsWith('/my-planning')) {
        event.respondWith(
            caches.match(request).then(response => {
                if (response) {
                    return response;
                }
                
                return fetch(request).then(fetchResponse => {
                    if (fetchResponse.ok) {
                        const responseClone = fetchResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, responseClone);
                        });
                    }
                    return fetchResponse;
                }).catch(() => {
                    // Return offline page if navigation fails
                    if (request.mode === 'navigate') {
                        return caches.match('/offline.html');
                    }
                });
            })
        );
    }
    
    // Cache API responses
    if (url.pathname.startsWith('/api/v1/offline/planning/')) {
        event.respondWith(
            caches.match(request).then(response => {
                if (response) {
                    // Return cached version and try to update in background
                    fetch(request).then(fetchResponse => {
                        if (fetchResponse.ok) {
                            caches.open(CACHE_NAME).then(cache => {
                                cache.put(request, fetchResponse.clone());
                            });
                        }
                    }).catch(() => {});
                    
                    return response;
                }
                
                return fetch(request).then(fetchResponse => {
                    if (fetchResponse.ok) {
                        const responseClone = fetchResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(request, responseClone);
                        });
                    }
                    return fetchResponse;
                });
            })
        );
    }
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
```

## Gebruikerservaring

### Offline Indicator

```html
<!-- Offline status indicator -->
<div x-data="{ 
    isOnline: navigator.onLine, 
    pendingSync: 0,
    syncInProgress: false
}" 
     x-init="
        $watch('isOnline', (value) => {
            if (value && pendingSync > 0) {
                window.offlinePlanningManager.attemptSync();
            }
        });
        
        setInterval(async () => {
            const counts = await window.offlinePlanningManager.getPendingSyncCount();
            pendingSync = counts.total;
        }, 5000);
     "
     @online.window="isOnline = true"
     @offline.window="isOnline = false"
     class="fixed top-4 right-4 z-50">
     
    <!-- Online status -->
    <div x-show="isOnline && pendingSync === 0" 
         class="bg-green-500 text-white px-3 py-2 rounded-lg shadow-lg">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
            </svg>
            Online
        </div>
    </div>
    
    <!-- Offline status -->
    <div x-show="!isOnline" 
         class="bg-red-500 text-white px-3 py-2 rounded-lg shadow-lg">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
            </svg>
            Offline
        </div>
    </div>
    
    <!-- Pending sync -->
    <div x-show="pendingSync > 0" 
         class="bg-orange-500 text-white px-3 py-2 rounded-lg shadow-lg">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-text="`${pendingSync} items te sync`"></span>
        </div>
    </div>
</div>
```

### Progressive Web App Manifest

```json
{
    "name": "Planning App",
    "short_name": "Planning",
    "description": "Offline planning application",
    "start_url": "/my-planning",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#3b82f6",
    "orientation": "portrait-primary",
    "icons": [
        {
            "src": "/images/icon-192.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/images/icon-512.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ],
    "offline_enabled": true,
    "cache_enabled": true
}
```

## Testing

### Unit Tests
```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Planning;
use App\Models\PlanningTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflinePlanningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_get_full_planning_data_for_offline_use()
    {
        $user = User::factory()->create();
        $planning = Planning::factory()->create();
        $planning->users()->attach($user);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson("/api/v1/offline/planning/{$planning->id}/full");
        
        $response->assertOk()
                ->assertJsonStructure([
                    'planning' => [
                        'id',
                        'planned_date',
                        'locations',
                        'planning_tasks'
                    ],
                    'offline_data' => [
                        'last_sync',
                        'sync_hash',
                        'expires_at'
                    ]
                ]);
    }
    
    /** @test */
    public function user_can_sync_offline_completions()
    {
        $user = User::factory()->create();
        $planning = Planning::factory()->create();
        $planningTask = PlanningTask::factory()->create(['planning_id' => $planning->id]);
        
        Sanctum::actingAs($user);
        
        $completionData = [
            'completions' => [
                [
                    'planning_task_id' => $planningTask->id,
                    'sync_hash' => 'offline_test_hash_123',
                    'comment' => 'Test completion offline',
                    'is_fully_completed' => true,
                    'completed_offline_at' => now()->toISOString()
                ]
            ]
        ];
        
        $response = $this->postJson('/api/v1/offline/sync/planning-tasks', $completionData);
        
        $response->assertOk();
        
        $this->assertDatabaseHas('planning_task_completions', [
            'planning_task_id' => $planningTask->id,
            'sync_hash' => 'offline_test_hash_123',
            'comment' => 'Test completion offline'
        ]);
    }
}
```

## Installatie & Setup

### Stappen

1. **Database migraties uitvoeren**
```bash
php artisan migrate
```

2. **Service Worker registreren**
```javascript
// In resources/js/app.js
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}
```

3. **PWA Manifest toevoegen**
```html
<!-- In resources/views/layouts/app.blade.php -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#3b82f6">
```

4. **Offline page aanmaken**
```html
<!-- public/offline.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Offline - Planning App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>Je bent offline</h1>
    <p>De app werkt beperkt offline. Zodra je weer online bent, worden je wijzigingen gesynchroniseerd.</p>
</body>
</html>
```

## Monitoring & Onderhoud

### Logging
```php
// In OfflineSyncController
Log::channel('offline_sync')->info('Sync completed', [
    'user_id' => $user->id,
    'completions_synced' => count($syncedCompletions),
    'photos_synced' => count($syncedPhotos),
    'sync_duration' => $syncDuration
]);
```

### Cleanup Job
```php
<?php

namespace App\Jobs;

use App\Models\OfflineSyncQueue;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class CleanupOfflineSyncQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle()
    {
        // Verwijder gesynchroniseerde items ouder dan 30 dagen
        OfflineSyncQueue::whereNotNull('synced_at')
            ->where('synced_at', '<', Carbon::now()->subDays(30))
            ->delete();
    }
}
```

Deze implementatie zorgt voor een robuuste offline-first ervaring waarbij gebruikers hun planningen kunnen uitvoeren zonder internetverbinding en automatische synchronisatie plaatsvindt zodra de verbinding weer beschikbaar is. 