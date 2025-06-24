<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('CSV Taken Import') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <!-- Import Form -->
                    <div class="mb-8">
                        <div class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                                CSV Bestand Uploaden
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                Upload een CSV bestand met taken. Alleen taken met activiteit type "To do" worden geïmporteerd.
                                <strong>Prioriteit mapping:</strong> "Laag" wordt "Normaal", "Hoog" blijft "Hoog".
                                <strong>Locaties:</strong> Alleen bestaande locaties uit de database worden gebruikt.
                                <strong>Titels:</strong> Korte omschrijvingen (≤50 tekens) worden gebruikt als titel, langere omschrijvingen krijgen een logische titel.
                            </p>
                        </div>

                        <form action="{{ route('csv-import.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            
                            <div>
                                <label for="csv_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CSV Bestand
                                </label>
                                <input 
                                    type="file" 
                                    name="csv_file" 
                                    id="csv_file"
                                    accept=".csv,.txt"
                                    class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 dark:file:bg-gray-700 dark:file:text-gray-200"
                                    required
                                >
                                @error('csv_file')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center space-x-4">
                                <button 
                                    type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                                >
                                    Importeren
                                </button>
                                
                                <a 
                                    href="{{ route('csv-import.template') }}"
                                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                                >
                                    Download Template
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Success Message -->
                    @if(session('success'))
                        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded dark:bg-green-800 dark:text-green-100 dark:border-green-600">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Import Results -->
                    @if(session('import_results'))
                        @php
                            $results = session('import_results');
                        @endphp
                        
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Import Resultaten</h3>
                            
                            <!-- Summary -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="bg-green-100 dark:bg-green-800 p-4 rounded-lg">
                                    <div class="text-green-800 dark:text-green-100 text-lg font-semibold">
                                        {{ $results['success_count'] }}
                                    </div>
                                    <div class="text-green-600 dark:text-green-200 text-sm">
                                        Succesvol geïmporteerd
                                    </div>
                                </div>
                                
                                @if($results['error_count'] > 0)
                                    <div class="bg-red-100 dark:bg-red-800 p-4 rounded-lg">
                                        <div class="text-red-800 dark:text-red-100 text-lg font-semibold">
                                            {{ $results['error_count'] }}
                                        </div>
                                        <div class="text-red-600 dark:text-red-200 text-sm">
                                            Fouten
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="bg-blue-100 dark:bg-blue-800 p-4 rounded-lg">
                                    <div class="text-blue-800 dark:text-blue-100 text-lg font-semibold">
                                        {{ count($results['imported_tasks']) }}
                                    </div>
                                    <div class="text-blue-600 dark:text-blue-200 text-sm">
                                        Nieuwe taken
                                    </div>
                                </div>
                            </div>

                            <!-- Imported Tasks List -->
                            @if(!empty($results['imported_tasks']))
                                <div class="mb-6">
                                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Geïmporteerde Taken</h4>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 max-h-64 overflow-y-auto">
                                        <div class="space-y-2">
                                            @foreach($results['imported_tasks'] as $task)
                                                <div class="flex justify-between items-center py-2 px-3 bg-white dark:bg-gray-600 rounded shadow-sm">
                                                    <div class="flex-1">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $task->title }}
                                                        </div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                                            {{ $task->location->name ?? 'Onbekende locatie' }}
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ 
                                                            match($task->priority) {
                                                                App\Enums\TaskPriority::HIGH => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                                App\Enums\TaskPriority::NORMAL => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                                App\Enums\TaskPriority::LOW => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                                default => 'bg-gray-100 text-gray-600'
                                                            }
                                                        }}">
                                                            {{ $task->priority->label() }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Errors List -->
                            @if(!empty($results['errors']))
                                <div class="mb-6">
                                    <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Fouten</h4>
                                    <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4 max-h-64 overflow-y-auto">
                                        <ul class="space-y-1">
                                            @foreach($results['errors'] as $error)
                                                <li class="text-sm text-red-700 dark:text-red-200">{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Instructions -->
                    <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100 mb-2">Instructies</h3>
                        <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                            <li>• Het CSV bestand moet de volgende kolommen bevatten: Locatie, Activiteit, Omschrijving, Prioriteit, Team 1, Geplande datum, Medewerker</li>
                            <li>• Alleen rijen met activiteit type "To do" (of variaties zoals "To Do", "todo", etc.) worden geïmporteerd</li>
                            <li>• Prioriteit "Laag" wordt omgezet naar "Normaal", "Hoog" blijft "Hoog"</li>
                            <li>• Locaties moeten exact overeenkomen met bestaande locaties in de database</li>
                            <li>• Als een locatie niet wordt gevonden, wordt de taak overgeslagen en een fout gerapporteerd</li>
                            <li>• Titels worden gegenereerd uit de omschrijving: korte omschrijvingen (≤50 tekens) worden gebruikt als titel</li>
                            <li>• Langere omschrijvingen krijgen een logische titel gebaseerd op actiewoorden (controleren, schoonmaken, etc.)</li>
                            <li>• Geplande datum kan in verschillende formaten (dd/mm/yyyy, dd-mm-yyyy, etc.)</li>
                            <li>• Download het template bestand voor een voorbeeld van het juiste formaat</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> 