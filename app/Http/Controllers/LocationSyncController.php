<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class LocationSyncController extends Controller
{
    /**
     * Trigger the synchronization of locations from the external API.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function syncNow(): RedirectResponse
    {
        try {
            Log::info('LocationSyncController: Manual synchronization triggered.');
            // Call the Artisan command
            $exitCode = Artisan::call('locations:sync');

            if ($exitCode === 0) {
                Log::info('LocationSyncController: Manual synchronization command completed successfully.');
                return redirect()->route('locations.index')
                                 ->with('success', 'Locatiesynchronisatie succesvol gestart en voltooid.');
            } else {
                Log::error('LocationSyncController: Manual synchronization command failed.', ['exit_code' => $exitCode]);
                $output = Artisan::output();
                Log::error('LocationSyncController: Command output: ' . $output);
                return redirect()->route('locations.index')
                                 ->with('error', "Fout bij het synchroniseren van locaties. Exit code: {$exitCode}. Bekijk de logs voor details.");
            }
        } catch (\Exception $e) {
            Log::error('LocationSyncController: Exception during manual synchronization.', ['exception' => $e]);
            return redirect()->route('locations.index')
                             ->with('error', "Er is een onverwachte fout opgetreden tijdens de synchronisatie: {$e->getMessage()}");
        }
    }
} 