# Database-Based Location Distance Cache

## Overzicht

Het systeem gebruikt nu een database-gebaseerde cache voor afstanden tussen locaties in plaats van browser localStorage. Dit biedt betere performance, persistentie en schaalbaarheid.

## Componenten

### 1. Database Schema

**Tabel: `location_distances`**
- `from_location_id` - Startlocatie
- `to_location_id` - Bestemmingslocatie  
- `distance_km` - Afstand in kilometers (decimal)
- `duration_minutes` - Reistijd in minuten (integer)
- `calculated_at` - Wanneer berekend (timestamp)
- `calculation_method` - Hoe berekend (google_maps, manual, etc.)
- `api_response` - Volledige API response voor debugging (JSON)

**Indexen:**
- Unique constraint op `from_location_id` + `to_location_id`
- Index voor snelle lookups
- Index op `calculated_at` voor cleanup

### 2. Model: `LocationDistance`

**Belangrijke methoden:**
- `getDistance($fromId, $toId)` - Bidirectionele lookup
- `storeDistance()` - Opslaan nieuwe afstand
- `getDistancesFrom($fromId)` - Alle afstanden vanaf locatie
- `isRecent($hours)` - Check of afstand recent berekend
- Formatted attributes voor weergave

### 3. Service: `LocationDistanceService`

**Kernfunctionaliteit:**
- Integreert met bestaande `TravelTimeService`
- Cache-first approach met fallback naar berekening
- Bidirectioneel opslaan (A→B en B→A)
- Batch processing met API rate limiting
- Cleanup van oude data

### 4. API Controller: `LocationDistanceController`

**Endpoints:**
- `GET /api/v1/location-distances/{id}/sorted` - Gesorteerde afstanden
- `POST /api/v1/location-distances/sort` - Sorteer locatie lijst
- `GET /api/v1/location-distances/{from}/to/{to}` - Specifieke afstand
- `POST /api/v1/location-distances/{from}/to/{to}/recalculate` - Herbereken
- `GET /api/v1/location-distances/stats` - Cache statistieken

### 5. Artisan Command: `distances:calculate`

**Opties:**
```bash
# Bereken alle ontbrekende afstanden
php artisan distances:calculate --missing-only

# Herbereken alles (forceer)
php artisan distances:calculate --force

# Bereken vanaf specifieke locaties
php artisan distances:calculate --from-location=1 --from-location=2

# Cleanup oude afstanden
php artisan distances:calculate --cleanup
```

### 6. Frontend JavaScript Service

**Nieuwe `LocationDistanceService` klasse:**
- Vervangt localStorage cache
- Async API calls naar database
- Fallback handling voor ontbrekende data
- Geïntegreerd met bestaande sorting functionaliteit

## Voordelen van Database Cache

### Performance
- **Snellere lookups**: Database indexen vs. localStorage parsing
- **Geen browser limits**: Geen 5-10MB localStorage beperkingen
- **Server-side processing**: Bulk operations op database niveau

### Betrouwbaarheid
- **Persistentie**: Data blijft bestaan tussen sessies en browsers
- **Consistentie**: Alle gebruikers delen dezelfde cache
- **Backup**: Onderdeel van database backups

### Schaalbaarheid
- **Geen duplicatie**: Elke afstand wordt eens opgeslagen voor alle gebruikers
- **Bulk berekening**: Vooraf berekenen van veel afstanden mogelijk
- **Rate limiting**: Centrale controle over API calls

### Onderhoud
- **Monitoring**: Cache statistieken via API
- **Cleanup**: Automatische verwijdering van oude data
- **Debugging**: API response data opgeslagen voor analyse

## Implementatie Details

### Cache Strategie
1. **Check database eerst** voor bestaande afstand
2. **Bereken alleen als nodig** (niet recent of niet aanwezig)
3. **Sla bidirectioneel op** (A→B en B→A)
4. **Respecteer API limits** met delays tussen calls

### Data Freshness
- **Recent threshold**: 1 week default
- **Automatic cleanup**: Oude data (30+ dagen) wordt verwijderd
- **Force recalculate**: Optie om specifieke afstanden te herberekenen

### Error Handling
- **Graceful degradation**: Fallback naar originele volgorde bij API fouten
- **Logging**: Alle fouten worden gelogd voor debugging
- **Retry logic**: Automatische herberekening bij volgende gebruik

## Migratie van localStorage

### Voor Gebruikers
- **Transparant**: Geen actie vereist van gebruikers
- **Betere performance**: Snellere sortering van locaties
- **Consistente data**: Zelfde afstanden voor alle gebruikers

### Voor Ontwikkelaars
- **Nieuwe API endpoints**: Gebruik database service in plaats van localStorage
- **Command line tools**: Artisan commands voor bulk operations
- **Monitoring**: Cache statistieken beschikbaar via API

## Onderhoud & Monitoring

### Daily Tasks
```bash
# Check cache coverage
php artisan distances:calculate --missing-only

# Cleanup oude data
php artisan distances:calculate --cleanup
```

### Monitoring Queries
```sql
-- Cache statistieken
SELECT 
  COUNT(*) as total_distances,
  COUNT(CASE WHEN calculated_at > NOW() - INTERVAL 7 DAY THEN 1 END) as recent,
  COUNT(CASE WHEN calculated_at < NOW() - INTERVAL 30 DAY THEN 1 END) as old
FROM location_distances;

-- Coverage per locatie
SELECT 
  l.name,
  COUNT(ld.id) as cached_distances
FROM locations l
LEFT JOIN location_distances ld ON l.id = ld.from_location_id
GROUP BY l.id, l.name
ORDER BY cached_distances DESC;
```

## Toekomstige Uitbreidingen

### Machine Learning
- **Route optimization**: Gebruik cached afstanden voor slimme route planning
- **Predictive caching**: Voorspel welke afstanden vaak gebruikt worden
- **Historical analysis**: Trends in reistijden over tijd

### Performance Optimizaties
- **Redis cache**: Laag tussen database en API voor ultra-snelle lookups
- **Background jobs**: Async berekening van ontbrekende afstanden
- **CDN caching**: Cache API responses op edge locations

### Integraties
- **Real-time traffic**: Dynamische updates gebaseerd op verkeersinformatie
- **Weather impact**: Aanpassingen voor weersomstandigheden
- **Alternative routes**: Meerdere route opties per locatie paar 