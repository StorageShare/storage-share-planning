<?php

namespace App\Console\Commands;

use App\Services\CsvTaskImportService;
use Illuminate\Console\Command;

class TestCsvImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv:test-import {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test CSV import functionality';

    /**
     * Execute the console command.
     */
    public function handle(CsvTaskImportService $csvImportService): int
    {
        $filePath = $this->argument('file');
        
        if (!$filePath) {
            // First, let's see what locations exist in the database
            $existingLocations = \App\Models\Location::limit(3)->pluck('name')->toArray();
            
            if (empty($existingLocations)) {
                $this->error('No locations found in database. Cannot test CSV import without existing locations.');
                return 1;
            }
            
            $this->info('Found existing locations: ' . implode(', ', $existingLocations));
            
            // Create a sample CSV content for testing using existing locations
            $csvContent = '"Locatie","Activiteit","Omschrijving","Prioriteit","Team 1","Geplande datum","Medewerker"' . "\n";
            $csvContent .= '"' . ($existingLocations[0] ?? 'Test Location') . '","To do","Controleronde uitvoeren en rapport uploaden","Hoog","","01/12/2024",""' . "\n";
            $csvContent .= '"' . ($existingLocations[1] ?? 'Test Location 2') . '","To do","Vloer schrobben","Laag","","",""' . "\n";
            $csvContent .= '"' . ($existingLocations[0] ?? 'Test Location') . '","To do","Even checken of ruimte 0.4 leeg staat","Hoog","","",""' . "\n";
            $csvContent .= '"' . ($existingLocations[1] ?? 'Test Location 2') . '","To do","Ruimte 20 en 45 controleren. Zou leeg moeten zijn.","Hoog","","",""' . "\n";
            $csvContent .= '"' . ($existingLocations[0] ?? 'Test Location') . '","To do","0.16 controleren op inhoud. Foto\'s maken","Laag","","",""' . "\n";
            $csvContent .= '"' . ($existingLocations[1] ?? 'Test Location 2') . '","To do","1.56 + 157 ontruimen → foto\'s maken van spullen","Hoog","","",""' . "\n";
            $csvContent .= '"Niet Bestaande Locatie","To do","Deze zou moeten falen","Hoog","","",""' . "\n";
            $csvContent .= '"' . ($existingLocations[0] ?? 'Test Location') . '","Controleronde","Dit wordt genegeerd","Hoog","","",""' . "\n";
            
            $this->info('Using sample CSV data for testing...');
        } else {
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }
            
            $csvContent = file_get_contents($filePath);
            $this->info("Reading CSV file: {$filePath}");
        }

        try {
            $csvData = $csvImportService->parseCsvContent($csvContent);
            
            $this->info('Parsed CSV data:');
            
            // Debug: Show first few rows with all columns
            if (!empty($csvData)) {
                $this->info('First row headers: ' . implode(', ', array_keys($csvData[0])));
                $this->info('First row data: ' . implode(', ', array_values($csvData[0])));
            }
            
            $this->table(['Index', 'Locatie', 'Activiteit', 'Prioriteit'], 
                collect($csvData)->map(function($row, $index) {
                    return [
                        $index,
                        $row['Locatie'] ?? 'N/A',
                        $row['Activiteit'] ?? 'N/A',
                        $row['Prioriteit'] ?? 'N/A'
                    ];
                })->toArray()
            );

            $results = $csvImportService->importTasks($csvData);
            
            $this->info("\nImport Results:");
            $this->info("Success: {$results['success_count']}");
            $this->info("Errors: {$results['error_count']}");
            
            if (!empty($results['errors'])) {
                $this->error("\nErrors:");
                foreach ($results['errors'] as $error) {
                    $this->error("- {$error}");
                }
            }
            
            if (!empty($results['imported_tasks'])) {
                $this->info("\nImported Tasks:");
                foreach ($results['imported_tasks'] as $task) {
                    $this->info("- {$task->title} ({$task->priority->label()}) at {$task->location->name}");
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
            return 1;
        }
    }
} 