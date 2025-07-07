<?php

namespace App\Console\Commands;

use App\Services\LocationDistanceService;
use App\Models\Location;
use App\Models\LocationDistance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateLocationDistances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'distances:calculate 
                            {--force : Herbereken alle afstanden, zelfs als ze al bestaan}
                            {--missing-only : Bereken alleen ontbrekende afstanden}
                            {--from-location=* : Bereken alleen afstanden vanaf specifieke locatie ID(s)}
                            {--cleanup : Verwijder oude afstanden na berekening}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bereken en cache alle afstanden tussen locaties in de database';

    protected LocationDistanceService $locationDistanceService;

    public function __construct(LocationDistanceService $locationDistanceService)
    {
        parent::__construct();
        $this->locationDistanceService = $locationDistanceService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starten met berekenen van locatie afstanden...');
        
        $force = $this->option('force');
        $missingOnly = $this->option('missing-only');
        $fromLocationIds = $this->option('from-location');
        $cleanup = $this->option('cleanup');

        // Valideer from-location IDs als opgegeven
        if (!empty($fromLocationIds)) {
            $validIds = Location::whereIn('id', $fromLocationIds)->pluck('id')->toArray();
            $invalidIds = array_diff($fromLocationIds, $validIds);
            
            if (!empty($invalidIds)) {
                $this->error('❌ Ongeldige locatie IDs gevonden: ' . implode(', ', $invalidIds));
                return 1;
            }
            
            $fromLocationIds = $validIds;
        }

        try {
            $startTime = now();
            
            if ($force && $missingOnly) {
                $this->error('❌ --force en --missing-only kunnen niet samen gebruikt worden');
                return 1;
            }

            // Toon huidige statistieken
            $this->showCurrentStats();

            if ($missingOnly) {
                $result = $this->calculateMissingDistances($fromLocationIds);
            } elseif (!empty($fromLocationIds)) {
                $result = $this->calculateFromSpecificLocations($fromLocationIds, !$force);
            } else {
                $result = $this->calculateAllDistances(!$force);
            }

            // Cleanup oude afstanden als gevraagd
            if ($cleanup) {
                $this->info('🧹 Verwijderen van oude afstanden...');
                $deletedCount = $this->locationDistanceService->cleanupOldDistances(30);
                $this->info("✅ {$deletedCount} oude afstanden verwijderd");
            }

            $this->displayResults($result, $startTime);
            
            // Toon nieuwe statistieken
            $this->newLine();
            $this->info('📊 Nieuwe statistieken:');
            $this->showCurrentStats();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Fout tijdens berekenen: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    private function showCurrentStats()
    {
        $totalDistances = LocationDistance::count();
        $recentDistances = LocationDistance::recent(168)->count(); // 1 week
        $totalLocations = Location::count();
        $maxPossible = $totalLocations * ($totalLocations - 1);
        $coverage = $maxPossible > 0 ? round(($totalDistances / $maxPossible) * 100, 1) : 0;

        $this->table(
            ['Statistiek', 'Waarde'],
            [
                ['Totaal locaties', $totalLocations],
                ['Cached afstanden', $totalDistances],
                ['Recente afstanden (1 week)', $recentDistances],
                ['Max mogelijke afstanden', $maxPossible],
                ['Coverage percentage', $coverage . '%'],
            ]
        );
    }

    private function calculateAllDistances(bool $skipExisting): array
    {
        $this->info($skipExisting ? 
            '📍 Berekenen van alle ontbrekende afstanden...' : 
            '📍 Herberekenen van alle afstanden...'
        );

        $locations = Location::all();
        $this->info("Gevonden {$locations->count()} locaties");

        if ($locations->count() < 2) {
            $this->warn('⚠️  Minimaal 2 locaties nodig voor afstand berekening');
            return ['calculated' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $totalPairs = $locations->count() * ($locations->count() - 1);
        $this->info("Te berekenen paren: {$totalPairs}");

        $progressBar = $this->output->createProgressBar($totalPairs);
        $progressBar->start();

        $calculated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($locations as $fromLocation) {
            foreach ($locations as $toLocation) {
                if ($fromLocation->id === $toLocation->id) {
                    continue;
                }

                // Check of we al een afstand hebben
                if ($skipExisting) {
                    $existing = LocationDistance::getDistance($fromLocation->id, $toLocation->id);
                    if ($existing && $existing->isRecent(168)) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }
                }

                $distance = $this->locationDistanceService->calculateAndStore($fromLocation->id, $toLocation->id);
                
                if ($distance) {
                    $calculated++;
                } else {
                    $errors++;
                }

                $progressBar->advance();

                // Kleine vertraging om API limits te respecteren
                usleep(100000); // 100ms
            }
        }

        $progressBar->finish();
        $this->newLine();

        return [
            'calculated' => $calculated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_locations' => $locations->count()
        ];
    }

    private function calculateMissingDistances(array $fromLocationIds = []): array
    {
        $this->info('📍 Berekenen van alleen ontbrekende afstanden...');

        $locations = empty($fromLocationIds) ? 
            Location::all() : 
            Location::whereIn('id', $fromLocationIds)->get();

        $calculated = 0;
        $errors = 0;

        foreach ($locations as $fromLocation) {
            // Vind locaties waarvoor we nog geen afstand hebben vanaf deze locatie
            $existingToIds = LocationDistance::where('from_location_id', $fromLocation->id)
                ->pluck('to_location_id')
                ->toArray();
            
            $allLocationIds = Location::where('id', '!=', $fromLocation->id)->pluck('id')->toArray();
            $missingLocationIds = array_diff($allLocationIds, $existingToIds);

            if (empty($missingLocationIds)) {
                $this->info("✅ Alle afstanden al berekend voor: {$fromLocation->name}");
                continue;
            }

            $this->info("📏 Berekenen van " . count($missingLocationIds) . " ontbrekende afstanden voor: {$fromLocation->name}");
            
            $progressBar = $this->output->createProgressBar(count($missingLocationIds));
            $progressBar->start();

            foreach ($missingLocationIds as $toLocationId) {
                $distance = $this->locationDistanceService->calculateAndStore($fromLocation->id, $toLocationId);
                
                if ($distance) {
                    $calculated++;
                } else {
                    $errors++;
                }

                $progressBar->advance();
                usleep(100000); // 100ms vertraging
            }

            $progressBar->finish();
            $this->newLine();
        }

        return [
            'calculated' => $calculated,
            'skipped' => 0,
            'errors' => $errors,
            'total_locations' => $locations->count()
        ];
    }

    private function calculateFromSpecificLocations(array $fromLocationIds, bool $skipExisting): array
    {
        $this->info('📍 Berekenen van afstanden vanaf specifieke locaties...');
        
        $locations = Location::whereIn('id', $fromLocationIds)->get();
        $this->info("Gevonden {$locations->count()} start locaties");

        $calculated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($locations as $fromLocation) {
            $this->info("📏 Berekenen vanaf: {$fromLocation->name}");
            
            $otherLocations = Location::where('id', '!=', $fromLocation->id)->get();
            $progressBar = $this->output->createProgressBar($otherLocations->count());
            $progressBar->start();

            foreach ($otherLocations as $toLocation) {
                // Check of we al een afstand hebben
                if ($skipExisting) {
                    $existing = LocationDistance::getDistance($fromLocation->id, $toLocation->id);
                    if ($existing && $existing->isRecent(168)) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }
                }

                $distance = $this->locationDistanceService->calculateAndStore($fromLocation->id, $toLocation->id);
                
                if ($distance) {
                    $calculated++;
                } else {
                    $errors++;
                }

                $progressBar->advance();
                usleep(100000); // 100ms vertraging
            }

            $progressBar->finish();
            $this->newLine();
        }

        return [
            'calculated' => $calculated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_locations' => $locations->count()
        ];
    }

    private function displayResults(array $result, $startTime)
    {
        $duration = $startTime->diffForHumans(now(), true);
        
        $this->newLine();
        $this->info('🎉 Afstand berekening voltooid!');
        $this->newLine();
        
        $this->table(
            ['Resultaat', 'Aantal'],
            [
                ['✅ Berekend', $result['calculated']],
                ['⏭️  Overgeslagen', $result['skipped']],
                ['❌ Fouten', $result['errors']],
                ['⏱️  Duur', $duration],
            ]
        );

        if ($result['errors'] > 0) {
            $this->warn("⚠️  Er zijn {$result['errors']} fouten opgetreden. Controleer de logs voor details.");
        }
    }
}
