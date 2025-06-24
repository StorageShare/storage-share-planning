<?php

namespace App\Http\Controllers;

use App\Services\CsvTaskImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CsvImportController extends Controller
{
    public function __construct(
        private CsvTaskImportService $csvImportService
    ) {}

    /**
     * Show the CSV import form.
     */
    public function show(): View
    {
        return view('csv-import.index');
    }

    /**
     * Handle the CSV import process.
     */
    public function import(Request $request): RedirectResponse
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240' // 10MB max
            ]
        ], [
            'csv_file.required' => 'Selecteer een CSV bestand om te uploaden.',
            'csv_file.file' => 'Het geüploade bestand is ongeldig.',
            'csv_file.mimes' => 'Het bestand moet een CSV bestand zijn.',
            'csv_file.max' => 'Het bestand mag niet groter zijn dan 10MB.'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Read CSV file content
            $csvFile = $request->file('csv_file');
            $csvContent = file_get_contents($csvFile->getPathname());

            // Parse CSV content
            $csvData = $this->csvImportService->parseCsvContent($csvContent);

            if (empty($csvData)) {
                return redirect()->back()
                    ->withErrors(['csv_file' => 'Het CSV bestand is leeg of heeft een ongeldig formaat.'])
                    ->withInput();
            }

            // Import tasks
            $importResults = $this->csvImportService->importTasks($csvData);

            // Flash results to session
            $message = "Import voltooid: {$importResults['success_count']} taken succesvol geïmporteerd.";
            
            if ($importResults['error_count'] > 0) {
                $message .= " {$importResults['error_count']} fouten opgetreden.";
            }

            session()->flash('import_results', $importResults);

            return redirect()->back()
                ->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['csv_file' => 'Er is een fout opgetreden bij het importeren: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Download sample CSV template.
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\Response
    {
        $headers = [
            'Locatie',
            'Activiteit', 
            'Omschrijving',
            'Prioriteit',
            'Team 1',
            'Geplande datum',
            'Medewerker'
        ];

        $sampleData = [
            [
                'Amsterdam Isolatorweg 30',
                'To do',
                'Controleronde uitvoeren en rapport uploaden',
                'Hoog',
                '',
                '01/12/2024',
                ''
            ],
            [
                'Soest Weteringpad 18',
                'To do',
                'Vloer schrobben met schrobmachine',
                'Laag',
                '',
                '15/12/2024',
                ''
            ]
        ];

        $csvContent = '';
        
        // Add headers
        $csvContent .= implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers)) . "\n";

        // Add sample data
        foreach ($sampleData as $row) {
            $csvContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="taken_import_template.csv"');
    }
} 