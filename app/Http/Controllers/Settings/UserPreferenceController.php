<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    /**
     * Get the active preference for a page.
     */
    public function show(Request $request, string $page): JsonResponse
    {
        $preference = UserPreference::getActive($request->user()->id, $page);

        return response()->json([
            'data' => $preference?->value,
        ]);
    }

    /**
     * Save/update the active preference for a page.
     *
     * Merges the incoming `value` object with any existing stored value so
     * that clients persisting a subset of fields (e.g. only `visibleColumns`)
     * don't clobber fields managed by a sibling client (e.g. `filters`,
     * `search`) on the same page.
     */
    public function update(Request $request, string $page): JsonResponse
    {
        $request->validate([
            'value' => 'required|array',
        ]);

        $existing = UserPreference::getActive($request->user()->id, $page);
        $existingValue = is_array($existing?->value) ? $existing->value : [];
        $mergedValue = array_merge($existingValue, $request->input('value'));

        $preference = UserPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'page' => $page,
                'name' => 'Default',
            ],
            [
                'value' => $mergedValue,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => $preference->value,
        ]);
    }
}
