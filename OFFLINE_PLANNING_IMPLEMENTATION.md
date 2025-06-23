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

## Implementatie Stappen

### 1. Database Setup
```bash
# Voer de migratie uit
php artisan migrate

# Controleer of de nieuwe tabellen bestaan
php artisan tinker
>>> Schema::hasTable('offline_sync_queue')
>>> Schema::hasColumn('planning_task_completions', 'sync_hash')
```

### 2. Service Worker Registratie
Voeg toe aan `resources/js/app.js`:
```javascript
import './offline-manager.js';

// Service Worker registratie
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

### 3. Layout Updates
Voeg toe aan `resources/views/layouts/app.blade.php`:
```html
<head>
    <!-- ... bestaande head content ... -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#3b82f6">
    <meta name="auth-token" content="{{ auth()->user()?->createToken('web')->plainTextToken ?? '' }}">
</head>
```

### 4. Offline Status Indicator
Voeg toe aan `resources/views/layouts/app.blade.php` (voor de body sluiting):
```html
<!-- Offline status indicator -->
<div x-data="offlineStatus()" 
     x-init="init()"
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

<script>
function offlineStatus() {
    return {
        isOnline: navigator.onLine,
        pendingSync: 0,
        syncInProgress: false,
        
        init() {
            // Listen to online/offline events
            window.addEventListener('online', () => {
                this.isOnline = true;
                if (window.offlinePlanningManager) {
                    window.offlinePlanningManager.attemptSync();
                }
            });
            
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });
            
            // Check pending sync count every 5 seconds
            setInterval(async () => {
                if (window.offlinePlanningManager) {
                    try {
                        const counts = await window.offlinePlanningManager.getPendingSyncCount();
                        this.pendingSync = counts.total;
                    } catch (error) {
                        console.error('Error getting pending sync count:', error);
                    }
                }
            }, 5000);
            
            // Listen to sync events
            if (window.offlinePlanningManager) {
                window.offlinePlanningManager.onSyncStatusChange((status) => {
                    this.syncInProgress = status.syncInProgress;
                });
            }
        }
    }
}
</script>
```

### 5. My-Planning View Updates
Voeg offline functionaliteit toe aan de bestaande my-planning view door de JavaScript te updaten:

```javascript
// In resources/views/my-planning/show.blade.php
// Voeg toe aan de locationPlanning Alpine.js component:

async submitCompletion(task) {
    const photos = this.completion.photos;
    const completionData = {
        comment: this.completion.notes,
        is_fully_completed: this.completion.is_fully_completed,
        task_duration_seconds: this.taskElapsedSeconds
    };
    
    try {
        // Probeer eerst online sync
        if (navigator.onLine) {
            const response = await fetch(`/plannings/${this.planningId}/tasks/${task.task_id}/submit-completion`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    ...completionData,
                    photos: photos // Dit zou een andere implementatie nodig hebben voor online
                })
            });
            
            if (response.ok) {
                // Online sync geslaagd
                this.handleCompletionSuccess(task);
                return;
            }
        }
        
        // Offline opslag
        if (window.offlinePlanningManager) {
            const syncHash = await window.offlinePlanningManager.saveTaskCompletion(
                task.task_id,
                completionData,
                photos
            );
            
            // Update UI optimistically
            task.status = 'completed_offline';
            task.sync_hash = syncHash;
            task.completed_notes = completionData.comment;
            
            this.showMessage('Taak opgeslagen offline. Wordt gesynchroniseerd zodra internet beschikbaar is.');
            this.handleCompletionSuccess(task);
        } else {
            throw new Error('Offline manager niet beschikbaar');
        }
    } catch (error) {
        console.error('Error submitting completion:', error);
        this.showMessage('Fout bij opslaan van taak: ' + error.message, 'error');
    }
},

async loadPlanningData() {
    // Probeer eerst cached data te laden
    if (window.offlinePlanningManager) {
        const cachedData = await window.offlinePlanningManager.getCachedPlanningData(this.planningId);
        if (cachedData) {
            console.log('Loaded cached planning data');
            // Update UI met cached data indien nodig
        }
        
        // Cache nieuwe data als online
        if (navigator.onLine) {
            try {
                await window.offlinePlanningManager.cachePlanningData(this.planningId);
                console.log('Planning data cached for offline use');
            } catch (error) {
                console.error('Error caching planning data:', error);
            }
        }
    }
}
```

## Testing

### Unit Tests
Maak `tests/Feature/OfflinePlanningTest.php`:
```php
<?php

namespace Tests\Feature;

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

### Browser Testing
1. Open DevTools → Application → Service Workers
2. Controleer of de service worker geregistreerd is
3. Ga naar Network tab → Schakel "Offline" in
4. Test de my-planning functionaliteiten
5. Controleer IndexedDB voor opgeslagen data
6. Schakel online terug en controleer synchronisatie

## Deployment

### Productie Setup
1. Zorg ervoor dat HTTPS enabled is (vereist voor Service Workers)
2. Genereer PWA iconen voor verschillende groottes
3. Update CSP headers voor Service Worker:
```php
// In middleware of headers
'Content-Security-Policy' => "script-src 'self' 'unsafe-inline'; worker-src 'self';"
```

### Monitoring
Voeg logging toe voor offline events:
```php
// In OfflineSyncController
Log::channel('offline_sync')->info('Sync completed', [
    'user_id' => $user->id,
    'completions_synced' => count($syncedCompletions),
    'photos_synced' => count($syncedPhotos),
    'sync_duration' => $syncDuration
]);
```

## Troubleshooting

### Veelvoorkomende Problemen

1. **Service Worker niet geregistreerd**
   - Controleer HTTPS verbinding
   - Controleer console voor errors
   - Verifieer pad naar sw.js

2. **IndexedDB errors**
   - Controleer browser ondersteuning
   - Clear browser storage en probeer opnieuw
   - Controleer quota limits

3. **Sync fails**
   - Controleer API endpoints
   - Verifieer CSRF token
   - Controleer network connectivity

4. **Photos niet synced**
   - Controleer file size limits
   - Verifieer base64 encoding
   - Controleer storage permissions

Deze implementatie zorgt voor een robuuste offline-first ervaring waarbij gebruikers hun planningen kunnen uitvoeren zonder internetverbinding en automatische synchronisatie plaatsvindt zodra de verbinding weer beschikbaar is. 