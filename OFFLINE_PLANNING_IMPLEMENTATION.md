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

## Implementatie stappen

Deze implementatie zorgt voor een robuuste offline-first ervaring waarbij gebruikers hun planningen kunnen uitvoeren zonder internetverbinding en automatische synchronisatie plaatsvindt zodra de verbinding weer beschikbaar is.

Wilt u dat ik doorgaan met de volledige implementatie van de code bestanden? 