<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Task;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CsvTaskImportService
{
    /**
     * Import tasks from CSV data.
     *
     * @param array $csvData
     * @return array
     */
    public function importTasks(array $csvData): array
    {
        $importResults = [
            'success_count' => 0,
            'error_count' => 0,
            'errors' => [],
            'imported_tasks' => []
        ];

        foreach ($csvData as $rowIndex => $row) {
            try {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                // Check if it's a "To do", "Schoonmaken", or "Controleronde" activity
                $activity = $row['Activiteit'] ?? '';
                if (!$this->isToDoActivity($activity) && !$this->isSchoonmakenActivity($activity) && !$this->isControlerondeActivity($activity)) {
                    continue;
                }

                if (empty($row['Locatie'])) {
                    Log::warning('Lege locatie bij import', ['rowIndex' => $rowIndex, 'row' => $row]);
                    $importResults['errors'][] = "Rij {$rowIndex}: Geen locatie opgegeven (inhoud: " . json_encode($row) . ")";
                    $importResults['error_count']++;
                    continue;
                }

                // Find existing location
                $location = $this->findLocation($row['Locatie']);
                
                if (!$location) {
                    $importResults['errors'][] = "Rij {$rowIndex}: Locatie '{$row['Locatie']}' niet gevonden in database";
                    $importResults['error_count']++;
                    continue;
                }

                // Check if this was a fallback match and warn the user
                $csvLocation = $row['Locatie'];
                $foundLocation = $location->name;
                $foundAddress = $location->address;
                
                // If the names don't match exactly, it was likely a fallback match
                if (strtolower(trim($csvLocation)) !== strtolower(trim($foundLocation))) {
                    $importResults['errors'][] = "Rij {$rowIndex}: Waarschuwing - Locatie '{$csvLocation}' werd gematcht met '{$foundLocation}' ({$foundAddress}) - controleer of dit correct is";
                    // Don't increment error_count for warnings, just show them
                }

                // Map priority
                $priority = $this->mapPriority($row['Prioriteit'] ?? '');

                // Generate title and description based on activity type
                if ($this->isSchoonmakenActivity($activity)) {
                    $title = 'Schoonmaken';
                    $description = $this->getFirstLineOfDescription($row['Omschrijving'] ?? '');
                    $isRecurring = true;
                    $recurringIntervalType = 'months';
                    $recurringIntervalValue = 3;
                    $benodigdheden = [];
                    $estimatedMinutes = 240; // 4 uur
                } elseif ($this->isControlerondeActivity($activity)) {
                    $title = 'Controleronde';
                    $description = $this->generateControlerondeDescription($row['Omschrijving'] ?? '');
                    $isRecurring = true;
                    $recurringIntervalType = 'months';
                    $recurringIntervalValue = 6;
                    $benodigdheden = $this->getControlerondeBenodigdheden($row['Omschrijving'] ?? '');
                    $estimatedMinutes = 240; // 4 uur
                } else {
                    $title = $this->generateTitleFromDescription($row['Omschrijving'] ?? '');
                    $description = $row['Omschrijving'] ?? '';
                    $isRecurring = false;
                    $recurringIntervalType = null;
                    $recurringIntervalValue = null;
                    $benodigdheden = [];
                    $estimatedMinutes = null;
                }
                
                // Create task
                $task = Task::create([
                    'location_id' => $location->id,
                    'title' => $title,
                    'description' => $description,
                    'priority' => $priority,
                    'status' => TaskStatus::OPEN,
                    'created_by' => Auth::id() ?? 1,
                    'deadline' => !empty($row['Geplande datum']) ? $this->parseDate($row['Geplande datum']) : null,
                    'is_recurring' => $isRecurring,
                    'recurring_interval_type' => $recurringIntervalType,
                    'recurring_interval_value' => $recurringIntervalValue,
                    'estimated_minutes' => $estimatedMinutes,
                ]);

                // Attach benodigdheden if any
                if (!empty($benodigdheden)) {
                    $task->benodigdheden()->attach($benodigdheden);
                }

                $importResults['imported_tasks'][] = $task;
                $importResults['success_count']++;

            } catch (\Exception $e) {
                Log::error('CSV import error for row ' . $rowIndex, [
                    'error' => $e->getMessage(),
                    'row' => $row
                ]);
                
                $importResults['errors'][] = "Rij {$rowIndex}: {$e->getMessage()}";
                $importResults['error_count']++;
            }
        }

        return $importResults;
    }

    /**
     * Check if the activity type is a "To do" type.
     *
     * @param string $activity
     * @return bool
     */
    private function isToDoActivity(string $activity): bool
    {
        $toDoVariations = [
            'to do',
            'todo',
            'to-do',
            'to_do',
            'todozan',
            'to dolan'
        ];

        $activity = strtolower(trim($activity));
        
        foreach ($toDoVariations as $variation) {
            if (str_contains($activity, $variation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find existing location by name.
     *
     * @param string $locationName
     * @return Location|null
     */
    private function findLocation(string $locationName): ?Location
    {
        $locationName = trim($locationName);
        
        if (empty($locationName)) {
            return null;
        }

        // Try to find existing location by exact name match first
        $location = Location::where('name', $locationName)->first();
        
        if ($location) {
            return $location;
        }
        
        // Try case-insensitive exact match
        $location = Location::whereRaw('LOWER(name) = ?', [strtolower($locationName)])->first();
        
        if ($location) {
            return $location;
        }

        // Try to match by address if the CSV contains a street name
        // This is more precise than just matching city names
        $words = explode(' ', $locationName);
        if (count($words) >= 2) {
            // Look for street name patterns (usually the last part after the city)
            $streetPart = end($words);
            $cityPart = implode(' ', array_slice($words, 0, -1));
            
            // Try to find by street name in address field
            $location = Location::where('address', 'LIKE', '%' . $streetPart . '%')
                              ->where(function($query) use ($cityPart) {
                                  $query->where('name', 'LIKE', '%' . $cityPart . '%')
                                        ->orWhere('address', 'LIKE', '%' . $cityPart . '%');
                              })
                              ->first();
            
            if ($location) {
                return $location;
            }
        }

        // Try partial match but be more strict - require at least 70% of the words to match
        $csvWords = array_filter(explode(' ', strtolower($locationName)), function($word) {
            return strlen($word) >= 3;
        });
        
        if (!empty($csvWords)) {
            $locations = Location::where(function($query) use ($csvWords) {
                foreach ($csvWords as $word) {
                    $query->where(function($q) use ($word) {
                        $q->where('name', 'LIKE', '%' . $word . '%')
                          ->orWhere('address', 'LIKE', '%' . $word . '%');
                    });
                }
            })->get();
            
            // Find the best match by calculating similarity
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($locations as $loc) {
                $locWords = array_filter(explode(' ', strtolower($loc->name . ' ' . $loc->address)), function($word) {
                    return strlen($word) >= 3;
                });
                
                $commonWords = array_intersect($csvWords, $locWords);
                $score = count($commonWords) / max(count($csvWords), count($locWords));
                
                if ($score > $bestScore && $score >= 0.7) {
                    $bestScore = $score;
                    $bestMatch = $loc;
                }
            }
            
            if ($bestMatch) {
                return $bestMatch;
            }
        }

        // Fallback: try simple partial match but log it for debugging
        $location = Location::where('name', 'LIKE', '%' . $locationName . '%')
                          ->orWhere('address', 'LIKE', '%' . $locationName . '%')
                          ->first();
        
        if ($location) {
            Log::warning('Location found with fallback matching', [
                'csv_location' => $locationName,
                'found_location' => $location->name,
                'found_address' => $location->address
            ]);
            return $location;
        }

        return null;
    }

    /**
     * Map CSV priority to system priority.
     *
     * @param string $csvPriority
     * @return TaskPriority
     */
    private function mapPriority(string $csvPriority): TaskPriority
    {
        $csvPriority = strtolower(trim($csvPriority));
        
        return match ($csvPriority) {
            'hoog' => TaskPriority::HIGH,
            'laag' => TaskPriority::NORMAL, // "Laag" should become "medium" (normal)
            default => TaskPriority::LOW, // Default to low priority
        };
    }

    /**
     * Parse date from various formats.
     *
     * @param string $dateString
     * @return \Carbon\Carbon|null
     */
    private function parseDate(string $dateString): ?\Carbon\Carbon
    {
        $dateString = trim($dateString);
        
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try common Dutch date formats
            $formats = [
                'd/m/Y',
                'd-m-Y',
                'Y-m-d',
                'd/m/y',
                'd-m-y',
            ];

            foreach ($formats as $format) {
                $date = \Carbon\Carbon::createFromFormat($format, $dateString);
                if ($date) {
                    return $date;
                }
            }

            // Fallback to Carbon's flexible parsing
            return \Carbon\Carbon::parse($dateString);
            
        } catch (\Exception $e) {
            Log::warning('Could not parse date: ' . $dateString, ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse CSV content from uploaded file.
     *
     * @param string $csvContent
     * @return array
     */
    public function parseCsvContent(string $csvContent): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csvimport_');
        file_put_contents($tmpFile, $csvContent);

        $data = [];
        $headers = null;

        $file = new \SplFileObject($tmpFile);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',');

        foreach ($file as $rowIndex => $row) {
            if ($row === null || (is_array($row) && count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0)) {
                continue;
            }
            if ($headers === null) {
                // Trim headers
                $headers = array_map(fn($h) => is_string($h) ? trim($h) : $h, $row);
                // Strip BOM from first header if present
                if (isset($headers[0])) {
                    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
                }
                continue;
            }
            if (count($row) === count($headers)) {
                // Trim values
                $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);
                $data[] = array_combine($headers, $row);
            }
        }

        @unlink($tmpFile);

        return $data;
    }

    /**
     * Generate title from description.
     *
     * @param string $description
     * @return string
     */
    private function generateTitleFromDescription(string $description): string
    {
        $description = trim($description);
        
        if (empty($description)) {
            return 'Geïmporteerde taak';
        }
        
        // If description is very short (max 30 characters), use it as title
        if (strlen($description) <= 30) {
            return $description;
        }
        
        // For longer descriptions, generate a logical title
        $words = explode(' ', $description);
        
        // Common action words to look for
        $actionWords = [
            'controleren', 'controleronde', 'schoonmaken', 'vegen', 'stofzuigen', 
            'schrobben', 'ontruimen', 'repareren', 'vervangen', 'ophangen',
            'plaatsen', 'installeren', 'afvoeren', 'opruimen', 'checken',
            'nakijken', 'uitzoeken', 'onderzoeken', 'verhelpen', 'oplossen'
        ];
        
        // Try to find an action word
        foreach ($actionWords as $actionWord) {
            foreach ($words as $index => $word) {
                if (stripos($word, $actionWord) !== false) {
                    // Found an action word, build title around it
                    $titleWords = [];
                    
                    // Add words before action word (up to 3 words back)
                    $startIndex = max(0, $index - 3);
                    for ($i = $startIndex; $i < $index; $i++) {
                        if (isset($words[$i])) {
                            $titleWords[] = $words[$i];
                        }
                    }
                    
                    // Add action word
                    $titleWords[] = $words[$index];
                    
                    // Add words after action word (up to 5 words) to catch numbers
                    $endIndex = min(count($words), $index + 6);
                    for ($i = $index + 1; $i < $endIndex; $i++) {
                        if (isset($words[$i])) {
                            $titleWords[] = $words[$i];
                        }
                    }
                    
                    $title = implode(' ', $titleWords);
                    
                    // Clean up the title
                    $title = preg_replace('/[.!?]+$/', '', $title);
                    $title = preg_replace('/\s+→\s+.*$/', '', $title);
                    $title = preg_replace('/\s+Zou\s+.*$/', '', $title);
                    $title = preg_replace('/\s+Foto.*$/', '', $title);
                    
                    // Check if title contains numbers - if so, allow longer titles
                    $containsNumbers = preg_match('/\d/', $title);
                    $maxLength = $containsNumbers ? 80 : 60;
                    
                    // If title is within limits, return it
                    if (strlen($title) <= $maxLength) {
                        return $title;
                    }
                    
                    // If too long but contains numbers, try to keep the numbers
                    if ($containsNumbers) {
                        // Look for specific patterns like "ruimte X.X"
                        if (preg_match('/(ruimte|unit|kamer|opslag|box)\s+(\d+\.?\d*)/i', $description, $matches)) {
                            $titleWords = [];
                            
                            // Add words before action word (up to 3 words back)
                            $startIndex = max(0, $index - 3);
                            for ($i = $startIndex; $i < $index; $i++) {
                                if (isset($words[$i])) {
                                    $titleWords[] = $words[$i];
                                }
                            }
                            
                            // Add action word
                            $titleWords[] = $words[$index];
                            
                            // Add the matched pattern
                            $titleWords[] = $matches[1] . ' ' . $matches[2];
                            
                            $title = implode(' ', $titleWords);
                            $title = preg_replace('/[.!?]+$/', '', $title);
                            
                            return $title;
                        }
                        
                        // If no specific pattern, just include the first number found
                        if (preg_match('/(\d+\.?\d*)/', $description, $matches)) {
                            $titleWords = [];
                            
                            // Add words before action word (up to 3 words back)
                            $startIndex = max(0, $index - 3);
                            for ($i = $startIndex; $i < $index; $i++) {
                                if (isset($words[$i])) {
                                    $titleWords[] = $words[$i];
                                }
                            }
                            
                            // Add action word
                            $titleWords[] = $words[$index];
                            
                            // Add the number
                            $titleWords[] = $matches[1];
                            
                            $title = implode(' ', $titleWords);
                            $title = preg_replace('/[.!?]+$/', '', $title);
                            
                            return $title;
                        }
                    }
                    
                    // If still too long, truncate but try to keep action word
                    $titleWords = [];
                    
                    // Add words before action word (up to 3 words back)
                    $startIndex = max(0, $index - 3);
                    for ($i = $startIndex; $i < $index; $i++) {
                        if (isset($words[$i])) {
                            $titleWords[] = $words[$i];
                        }
                    }
                    
                    // Add action word
                    $titleWords[] = $words[$index];
                    
                    $title = implode(' ', $titleWords);
                    $title = preg_replace('/[.!?]+$/', '', $title);
                    
                    return $title;
                }
            }
        }
        
        // If no action word found, take first 3-4 meaningful words
        $meaningfulWords = array_filter($words, function($word) {
            return strlen($word) >= 3 && !in_array(strtolower($word), ['van', 'de', 'het', 'een', 'met', 'voor', 'naar', 'bij', 'op', 'in', 'uit', 'aan', 'om', 'door', 'over', 'onder', 'boven', 'achter', 'voor', 'na', 'tot', 'sinds', 'tijdens', 'zonder', 'met', 'tegen', 'langs', 'rond', 'omheen']);
        });
        
        $titleWords = array_slice($meaningfulWords, 0, 4);
        $title = implode(' ', $titleWords);
        
        // If still too long, truncate
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        
        return $title ?: 'Geïmporteerde taak';
    }

    /**
     * Check if a row is empty (all fields are empty or null).
     *
     * @param array $row
     * @return bool
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!empty(trim($value ?? ''))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the activity type is a "Schoonmaken" type.
     *
     * @param string $activity
     * @return bool
     */
    private function isSchoonmakenActivity(string $activity): bool
    {
        $schoonmakenVariations = [
            'schoonmaken',
            'schoonmaak',
            'schoon',
            'vegen',
            'stofzuigen',
            'schrobben'
        ];

        $activity = strtolower(trim($activity));
        
        foreach ($schoonmakenVariations as $variation) {
            if (str_contains($activity, $variation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the activity type is a "Controleronde" type.
     *
     * @param string $activity
     * @return bool
     */
    private function isControlerondeActivity(string $activity): bool
    {
        $controlerondeVariations = [
            'controleronde',
            'controleron',
            'controler',
            'control',
            'controlen',
            'controleronder',
            'controleronderde',
            'controleronderden',
            'controleronderdeel',
            'controleronderdeels'
        ];

        $activity = strtolower(trim($activity));
        
        foreach ($controlerondeVariations as $variation) {
            if (str_contains($activity, $variation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the first line of the description.
     *
     * @param string $description
     * @return string
     */
    private function getFirstLineOfDescription(string $description): string
    {
        $lines = explode("\n", $description);
        return trim($lines[0] ?? '');
    }

    /**
     * Generate description for Controleronde activity.
     *
     * @param string $description
     * @return string
     */
    private function generateControlerondeDescription(string $description): string
    {
        $baseDescription = "Voer een controleronde uit en noteer alle bijzonderheden en voeg voor elke bijzonderheid foto's toe!";
        
        // Check if the description contains "vanaf boven in de ruimtes kijken"
        if (str_contains(strtolower($description), 'vanaf boven in de ruimtes kijken')) {
            return "Voer een controleronde uit (vanaf boven in de ruimtes kijken) en noteer alle bijzonderheden en voeg voor elke bijzonderheid foto's toe!";
        }
        
        return $baseDescription;
    }

    /**
     * Get benodigdheden for Controleronde activity.
     *
     * @param string $description
     * @return array
     */
    private function getControlerondeBenodigdheden(string $description): array
    {
        $benodigdheden = [];
        
        // Check if the description contains "vanaf boven in de ruimtes kijken"
        if (str_contains(strtolower($description), 'vanaf boven in de ruimtes kijken')) {
            // Find the Selfiestick benodigheid
            $selfiestick = \App\Models\Benodigdheid::where('naam', 'Selfiestick')->first();
            if ($selfiestick) {
                $benodigdheden[] = $selfiestick->id;
            }
        }
        
        return $benodigdheden;
    }
}