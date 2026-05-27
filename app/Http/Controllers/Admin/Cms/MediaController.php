<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\MediaUpdateRequest;
use App\Http\Requests\Admin\Cms\MediaUploadRequest;
use App\Models\MediaAsset;
use App\Support\Cms\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    private const PER_PAGE = 36;

    public function __construct(private readonly MediaService $media) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));

        $query = MediaAsset::query()->whereNull('tenant_id');

        if ($search !== '') {
            $query->where('filename', 'ilike', "%{$search}%");
        }

        $paginator = $query->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        return Inertia::render('admin/cms/media/index', [
            'assets' => [
                'data' => $paginator->getCollection()->map(fn (MediaAsset $a) => $this->serialize($a))->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            'search' => $search,
        ]);
    }

    public function store(MediaUploadRequest $request): JsonResponse|RedirectResponse
    {
        $asset = $this->media->upload($request->file('file'), $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json(['asset' => $this->serialize($asset)]);
        }

        return back();
    }

    public function update(MediaUpdateRequest $request, MediaAsset $mediaAsset): RedirectResponse
    {
        $metadata = (array) ($mediaAsset->metadata ?? []);
        $metadata['alt'] = $request->input('alt', $metadata['alt'] ?? '');
        if ($request->has('focal_x')) {
            $metadata['focal_x'] = (float) $request->input('focal_x');
        }
        if ($request->has('focal_y')) {
            $metadata['focal_y'] = (float) $request->input('focal_y');
        }
        $mediaAsset->metadata = $metadata;
        $mediaAsset->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Updated.')]);

        return back();
    }

    public function destroy(MediaAsset $mediaAsset): RedirectResponse
    {
        $this->media->delete($mediaAsset);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'filename' => $asset->filename,
            'mime_type' => $asset->mime_type,
            'size_bytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'url' => $this->media->urlFor($asset),
            'metadata' => $asset->metadata ?? [],
            'created_at' => optional($asset->created_at)->toIso8601String(),
        ];
    }
}
