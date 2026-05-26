<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Privacy\DataExportShowRequest;
use App\Http\Requests\Settings\Privacy\DataExportStoreRequest;
use App\Jobs\GenerateDataExport;
use App\Models\DataExportRequest as DataExportRequestModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportController extends Controller
{
    /**
     * Show the privacy settings page — request a new export, see prior ones.
     */
    public function index(Request $request): Response
    {
        $exports = DataExportRequestModel::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'status', 'format', 'file_size_bytes', 'processed_at', 'expires_at', 'created_at'])
            ->map(function (DataExportRequestModel $row): array {
                $downloadUrl = null;
                if ($row->status === 'ready' && $row->expires_at !== null && $row->expires_at->isFuture()) {
                    $downloadUrl = URL::temporarySignedRoute(
                        'privacy.exports.download',
                        $row->expires_at,
                        ['export' => $row->id],
                    );
                }

                return [
                    'id' => (int) $row->id,
                    'status' => (string) $row->status,
                    'format' => (string) $row->format,
                    'file_size_bytes' => $row->file_size_bytes,
                    'processed_at' => optional($row->processed_at)->toIso8601String(),
                    'expires_at' => optional($row->expires_at)->toIso8601String(),
                    'created_at' => optional($row->created_at)->toIso8601String(),
                    'download_url' => $downloadUrl,
                ];
            })
            ->all();

        return Inertia::render('settings/privacy', [
            'exports' => $exports,
        ]);
    }

    /**
     * Queue a new data-export job for the current user.
     */
    public function store(DataExportStoreRequest $request): RedirectResponse
    {
        $export = DataExportRequestModel::query()->create([
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->current_tenant_id,
            'status' => 'requested',
            'format' => 'zip',
            'requested_ip' => $request->ip(),
        ]);

        GenerateDataExport::dispatch($export->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Data export queued. You\'ll receive an email when it\'s ready.'),
        ]);

        return back();
    }

    /**
     * Stream the generated archive to the user (signed URL only).
     */
    public function download(DataExportShowRequest $request, DataExportRequestModel $export): BinaryFileResponse
    {
        abort_unless($export->status === 'ready' && $export->file_path !== null, 404);
        abort_if($export->expires_at !== null && $export->expires_at->isPast(), 410);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($export->file_path), 404);

        $export->update(['downloaded_at' => now()]);

        return response()->download(
            $disk->path($export->file_path),
            'data-export-'.$export->id.'.zip',
            ['Content-Type' => 'application/zip'],
        );
    }
}
