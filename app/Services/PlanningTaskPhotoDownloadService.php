<?php

namespace App\Services;

use App\Models\PlanningTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PlanningTaskPhotoDownloadService
{
    public function download(PlanningTask $planningTask): BinaryFileResponse|RedirectResponse
    {
        $files = [];

        $planningTask->loadMissing('planningTaskPhotos');
        foreach ($planningTask->planningTaskPhotos as $photo) {
            if (! empty($photo->path) && Storage::disk('public')->exists($photo->path)) {
                $files[] = [
                    'disk_path' => Storage::disk('public')->path($photo->path),
                    'name' => 'task-photos/'.($photo->original_name ?: basename($photo->path)),
                ];
            }
        }

        $planningTask->loadMissing(['completions.photos']);
        foreach ($planningTask->completions as $completion) {
            foreach ($completion->photos as $photo) {
                $path = $photo->file_path ?? null;
                if ($path && Storage::disk('public')->exists($path)) {
                    $files[] = [
                        'disk_path' => Storage::disk('public')->path($path),
                        'name' => 'completion-photos/'.basename($path),
                    ];
                }
            }
        }

        if (empty($files)) {
            return back()->with('status', 'Geen foto\'s gevonden voor deze taak.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ptphotos_');
        $zipPath = $tmp.'.zip';
        if (file_exists($tmp) && ! str_ends_with($tmp, '.zip')) {
            @unlink($tmp);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Kon ZIP-bestand niet aanmaken.');
        }

        foreach ($files as $f) {
            $name = $f['name'];
            $uniqueName = $name;
            $counter = 1;
            while ($zip->locateName($uniqueName) !== false) {
                $pathInfo = pathinfo($name);
                $uniqueName = $pathInfo['dirname'].'/'.$pathInfo['filename'].'('.$counter.').'.($pathInfo['extension'] ?? '');
                $counter++;
            }

            $zip->addFile($f['disk_path'], $uniqueName);
        }

        $zip->close();

        return response()->download($zipPath, 'planning-task-'.$planningTask->id.'-photos.zip')->deleteFileAfterSend(true);
    }
}
