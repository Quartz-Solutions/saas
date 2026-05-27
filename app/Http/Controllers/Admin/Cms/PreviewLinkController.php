<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\PreviewController;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class PreviewLinkController extends Controller
{
    /**
     * Mints a signed preview URL for the given page so the admin
     * editor can drop it into an <iframe>.
     */
    public function show(CmsPage $cmsPage): JsonResponse
    {
        $url = PreviewController::signedUrlFor($cmsPage->id, 30);

        return response()->json(['url' => $url, 'expires_in_seconds' => 30 * 60]);
    }
}
