<?php

namespace App\Services;

use App\Models\PlanningTask;

class PlanningTaskHistoryService
{
    public function appendCompletionHistory(PlanningTask $planningTask, ?string $existingDescription): string
    {
        $history = "--- Vorige pogingen (meest recent eerst) ---\n\n";
        $completions = $planningTask->completions()->with('user')->orderBy('created_at', 'desc')->get();

        if ($completions->isEmpty()) {
            return $existingDescription ?? '';
        }

        foreach ($completions as $completion) {
            $outcome = $completion->review_outcome ? ' -> Oordeel: '.ucfirst($completion->review_outcome) : '';
            $history .= "----------------------------------------\n";
            $history .= 'Datum: '.$completion->created_at->format('d-m-Y H:i')."\n";
            $history .= 'Gebruiker: '.($completion->user->name ?? 'Onbekend')."\n";
            $history .= 'Notities: '.($completion->comment ?? 'Geen notities.')."\n";
            if ($completion->review_notes) {
                $history .= 'Review Notities: '.$completion->review_notes.$outcome."\n";
            }
        }

        return ($existingDescription ? $existingDescription."\n\n" : '').$history;
    }
}
