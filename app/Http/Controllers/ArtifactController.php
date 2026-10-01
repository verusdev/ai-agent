<?php

namespace App\Http\Controllers;

use App\Models\AgentArtifact;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArtifactController extends Controller
{
    public function show(Request $request, AgentArtifact $artifact): Response
    {
        $agent = $artifact->agent;
        $absolute = app(Workspace::class)->resolve($agent, $artifact->path);

        if (! is_file($absolute)) {
            abort(404, 'Файл артефакта не найден');
        }

        $content = (string) file_get_contents($absolute);
        $asDownload = $request->boolean('download') || $request->route('download');

        if ($asDownload) {
            return response($content, 200, [
                'Content-Type' => $artifact->mime,
                'Content-Disposition' => 'attachment; filename="'.addslashes($artifact->name).'"',
            ]);
        }

        if (str_starts_with($artifact->mime, 'text/')) {
            return response($content, 200, ['Content-Type' => $artifact->mime.'; charset=UTF-8']);
        }

        return response($content, 200, ['Content-Type' => $artifact->mime]);
    }
}