<?php

namespace App\Http\Controllers\Export;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ZipExportController extends Controller
{
    public function download(Project $project): BinaryFileResponse
    {
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Unauthorized.');
        }

        if (!in_array($project->status, [ProjectStatusEnum::Generated, ProjectStatusEnum::Exported])) {
            abort(404, 'No generated output available.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'draplo_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($project->generation_output as $file) {
            $zip->addFromString($file['path'], $file['content']);
        }

        $zip->close();

        $project->update(['exported_at' => now()]);

        return response()->download($zipPath, "{$project->slug}.zip")
            ->deleteFileAfterSend(true);
    }
}
