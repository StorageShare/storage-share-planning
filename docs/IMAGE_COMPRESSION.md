# Image Compression Feature

Deze documentatie beschrijft de automatische foto compressie functionaliteit die is geïmplementeerd in de StorageShare Planning applicatie.

## Overzicht

Alle geüploade foto's worden automatisch gecomprimeerd naar maximaal 2MB om opslagruimte te besparen en de prestaties te verbeteren. De compressie gebeurt server-side met behulp van de Intervention Image library.

## Implementatie

### 1. ImageService
- **Locatie**: `app/Services/ImageService.php`
- **Functionaliteit**: 
  - Comprimeren van afbeeldingen naar maximaal 2MB
  - Ondersteuning voor JPG, JPEG, PNG, GIF en WebP formaten
  - Automatische kwaliteitsvermindering en resize indien nodig
  - Fallback mechanisme bij compressiefouten

### 2. Geüpdatete Controllers
De volgende controllers zijn bijgewerkt om de ImageService te gebruiken:

- `app/Http/Controllers/Api/V1/PlanningTaskController.php`
- `app/Http/Controllers/PlanningTaskController.php`
- `app/Http/Controllers/Api/V1/TaskPhotoController.php`

### 3. Configuratie
- **Locatie**: `config/image.php`
- **Instellingen**:
  - Maximale bestandsgrootte: 2MB (configureerbaar)
  - Kwaliteitsinstellingen: start 90%, minimum 50%
  - Resize ratio: minimum 30% van origineel
  - Fallback mechanisme ingeschakeld

### 4. Validatieregels
- Upload limiet verhoogd naar 20MB om ruimte te bieden voor compressie
- Ondersteuning toegevoegd voor WebP formaat
- Gebruikersvriendelijke foutmeldingen

## Configuratie Opties

### Environment Variables
```bash
# Maximale bestandsgrootte na compressie (bytes)
IMAGE_MAX_SIZE_BYTES=2097152

# Compressie kwaliteitsinstellingen
IMAGE_INITIAL_QUALITY=90
IMAGE_MINIMUM_QUALITY=50
IMAGE_QUALITY_STEP=10

# Resize instellingen
IMAGE_MINIMUM_RESIZE_RATIO=0.3
IMAGE_RESIZE_STEP=0.1

# Storage instellingen
IMAGE_STORAGE_DISK=public
IMAGE_FALLBACK_ENABLED=true
```

## Hoe het werkt

1. **Upload**: Gebruiker uploadt een foto (tot 20MB)
2. **Compressie**: ImageService comprimeert de foto:
   - Start met 90% kwaliteit
   - Vermindert kwaliteit stapsgewijs als bestand te groot is
   - Verkleint afbeelding als minimale kwaliteit bereikt is
   - Stopt bij 30% van originele grootte of 2MB limiet
3. **Opslag**: Gecomprimeerde foto wordt opgeslagen
4. **Fallback**: Bij compressiefout wordt originele foto opgeslagen met logging

## Ondersteunde Formaten

- **Input**: JPG, JPEG, PNG, GIF, WebP (tot 20MB)
- **Output**: Zelfde formaat als input, gecomprimeerd tot max 2MB

## Prestaties

- **Geheugengebruik**: Optimaal door stapsgewijze compressie
- **Verwerkingstijd**: Afhankelijk van originele bestandsgrootte
- **Opslag**: Gemiddeld 70-90% ruimtebesparing

## Testing

Unit tests zijn beschikbaar in `tests/Unit/Services/ImageServiceTest.php`:

```bash
php artisan test --filter=ImageServiceTest
```

## Monitoring

Compressiefouten worden gelogd naar de applicatie logs:
```php
Log::error('Error compressing image: '.$e->getMessage());
```

## Troubleshooting

### Veelvoorkomende problemen:

1. **Memory limit errors**: Verhoog PHP memory_limit voor grote afbeeldingen
2. **GD extension missing**: Zorg dat PHP GD extension geïnstalleerd is
3. **Compressie mislukt**: Fallback mechanisme slaat originele foto op

### Log controle:
```bash
tail -f storage/logs/laravel.log | grep "Error compressing image"
```

## Toekomstige verbeteringen

- WebP conversie voor betere compressie
- Async verwerking voor grote bestanden
- Progress indicators voor gebruikers
- Admin dashboard voor compressie statistieken 