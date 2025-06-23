# Terugkerende Taken - Implementatie Documentatie

## Overzicht

Dit systeem biedt volledige ondersteuning voor terugkerende taken die automatisch opnieuw worden aangemaakt na goedkeuring door de admin. Het systeem ondersteunt intervals in dagen, weken, maanden en jaren.

## Database Wijzigingen

### Nieuwe Kolommen in `tasks` tabel:
- `is_recurring` (boolean) - Of de taak terugkerend is
- `recurring_interval_type` (enum) - Type interval: 'days', 'weeks', 'months', 'years'
- `recurring_interval_value` (integer) - Waarde van het interval (bijv. 2 voor "elke 2 weken")
- `parent_recurring_task_id` (foreign key) - Verwijzing naar de oorspronkelijke terugkerende taak
- `next_recurring_date` (timestamp) - Wanneer de volgende instantie moet worden aangemaakt
- `is_recurring_active` (boolean) - Of de herhaling actief is (kan gepauzeerd worden)

## Architectuur

### 1. RecurringTaskService (`app/Services/RecurringTaskService.php`)
Centrale service voor het beheren van terugkerende taken:
- `processCompletedTask()` - Verwerkt een voltooide taak en maakt nieuwe instantie aan
- `getDueRecurringTasks()` - Haalt alle taken op die klaar zijn voor herhaling
- `processAllDueRecurringTasks()` - Verwerkt alle due taken (gebruikt door scheduler)
- `stopRecurring()` / `resumeRecurring()` - Pauzeert/hervat terugkerende taken

### 2. Task Model Uitbreidingen (`app/Models/Task.php`)
Nieuwe methods en relationships:
- `calculateNextRecurringDate()` - Berekent volgende herhaling datum
- `getRecurringIntervalDescription()` - Menselijk leesbare beschrijving
- `createRecurringInstance()` - Maakt nieuwe taak instantie aan
- `parentRecurringTask()` / `childRecurringTasks()` - Relationships

### 3. Console Command (`app/Console/Commands/ProcessRecurringTasks.php`)
- Command: `php artisan tasks:process-recurring`
- Options: `--dry-run` voor testing zonder aanmaken
- Automatisch gepland om dagelijks om 06:00 te draaien

## User Interface

### Taak Formulier (`resources/views/tasks/_form.blade.php`)
Nieuwe sectie "Terugkerende Taak" met:
- Checkbox om terugkerende taak in te schakelen
- Interval waarde input (1-365)
- Interval type dropdown (dagen/weken/maanden/jaren)
- Live preview van herhaling beschrijving
- JavaScript voor dynamische UI updates

### Taak Details (`resources/views/tasks/show.blade.php`)
Toont recurring informatie:
- Interval beschrijving met emoji 🔄
- Volgende herhaling datum
- Status (actief/gepauzeerd) ⏸️
- Link naar hoofdtaak (voor instanties)

## Validatie

### StoreTaskRequest & UpdateTaskRequest
Nieuwe validatie regels:
- `is_recurring` - boolean, optioneel
- `recurring_interval_type` - verplicht als is_recurring=1, moet 'days', 'weeks', 'months', of 'years' zijn
- `recurring_interval_value` - verplicht als is_recurring=1, integer tussen 1-365

## Workflow

### Taak Aanmaken
1. Gebruiker maakt taak aan met recurring opties
2. Als recurring is ingeschakeld: interval type en waarde worden opgeslagen
3. `is_recurring_active` wordt standaard op `true` gezet

### Taak Goedkeuring (Aangepast in `TaskController::approve()`)
1. Admin keurt taak goed → status wordt `COMPLETED`
2. **NIEUW**: `RecurringTaskService::processCompletedTask()` wordt aangeroepen
3. Als taak terugkerend is:
   - Volgende deadline wordt berekend vanaf goedkeuring datum
   - Nieuwe taak instantie wordt aangemaakt met:
     - Kopie van alle taak data (titel, beschrijving, locatie, etc.)
     - `parent_recurring_task_id` verwijst naar originele taak
     - Status: `OPEN`
     - Nieuwe deadline
   - Benodigdheden worden gekopieerd
4. Success message toont of nieuwe taak is aangemaakt

### Automatische Verwerking
1. **Dagelijks om 06:00**: `tasks:process-recurring` command draait
2. Zoekt naar voltooide recurring tasks waar `next_recurring_date <= now()`
3. Maakt nieuwe instanties aan voor due taken
4. Logs resultaten voor monitoring

## Gebruik

### Een Terugkerende Taak Aanmaken
1. Ga naar "Nieuwe Taak"
2. Vul normale taak gegevens in
3. Vink "Deze taak is terugkerend" aan
4. Kies interval (bijv. "2" en "weken" voor elke 2 weken)
5. Bekijk live preview: "📅 Deze taak wordt elke 2 weken automatisch opnieuw aangemaakt na goedkeuring"
6. Sla op

### Terugkerende Taken Beheren
```bash
# Bekijk welke taken zouden worden verwerkt (zonder aanmaken)
php artisan tasks:process-recurring --dry-run

# Handmatig verwerken van due recurring tasks
php artisan tasks:process-recurring

# Scheduler status controleren
php artisan schedule:list
```

### Database Queries
```sql
-- Alle actieve terugkerende taken
SELECT * FROM tasks WHERE is_recurring = 1 AND is_recurring_active = 1;

-- Taken die due zijn voor herhaling
SELECT * FROM tasks 
WHERE is_recurring = 1 
  AND is_recurring_active = 1 
  AND parent_recurring_task_id IS NULL 
  AND status = 'completed' 
  AND next_recurring_date <= NOW();

-- Instanties van een terugkerende taak
SELECT * FROM tasks WHERE parent_recurring_task_id = [TASK_ID];
```

## Testing

### Handmatige Tests
1. **Taak Aanmaken**: Maak een terugkerende taak aan met korte interval (bijv. 1 dag)
2. **Goedkeuring**: Plan de taak en laat hem uitvoeren/goedkeuren
3. **Verificatie**: Controleer dat nieuwe instantie wordt aangemaakt met juiste deadline
4. **Command Test**: Gebruik `--dry-run` om te testen welke taken verwerkt zouden worden

### Scheduler Setup
Voor productie, zorg dat Laravel's task scheduler draait:
```bash
# Voeg toe aan crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Technische Details

### Datum Berekening
- Gebruikt PHP's `DateTime` en `DateInterval` classes
- Ondersteunt schrikkeljaren en maand-overgangen correct
- Voorbeelden:
  - Elke 2 weken: `P14D` (14 dagen)
  - Elke 3 maanden: `P3M`
  - Elk jaar: `P1Y`

### Performance Overwegingen
- Database indexen op `is_recurring`, `is_recurring_active`, en `next_recurring_date`
- Scheduler met `onOneServer()` en `withoutOverlapping()` voorkomt duplicate processing
- Service laadt alleen relevante taken (geen N+1 queries)

### Fout Afhandeling
- Try/catch in service methods met logging
- Graceful failure: als één taak faalt, gaan anderen door
- Command rapporteert errors en successes

## Uitbreidingsmogelijkheden

### Toekomstige Features
1. **Recurring Pauzeren/Hervatten**: UI voor admins om recurring uit/aan te zetten
2. **Maximaal Aantal**: Beperk aantal keren dat taak herhaald wordt
3. **Weekend Skip**: Sla weekenden over voor recurring deadlines
4. **Notification**: Email notificaties bij nieuwe recurring instances
5. **Dashboard**: Overzicht van alle recurring taken en hun status

### Configuratie Opties
Voeg toe aan `config/tasks.php`:
```php
return [
    'recurring' => [
        'max_instances' => 100, // Maximaal aantal instanties per recurring task
        'cleanup_after_days' => 365, // Ruim oude instanties op na X dagen
        'notification_email' => env('RECURRING_TASKS_EMAIL'),
    ]
];
```

Deze implementatie biedt een robuust en gebruiksvriendelijk systeem voor terugkerende taken dat naadloos integreert met de bestaande task workflow. 