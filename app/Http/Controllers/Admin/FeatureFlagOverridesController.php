<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureFlagOverrideDestroyRequest;
use App\Http\Requests\Admin\FeatureFlagOverrideStoreRequest;
use App\Http\Requests\Admin\FeatureFlagOverrideUpdateRequest;
use App\Models\FeatureFlag;
use App\Models\FeatureFlagOverride;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FeatureFlagOverridesController extends Controller
{
    public function store(FeatureFlagOverrideStoreRequest $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $validated = $request->validated();

        FeatureFlagOverride::create([
            'feature_flag_id' => $featureFlag->id,
            'tenant_id' => $validated['tenant_id'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'enabled' => (bool) $validated['enabled'],
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by_id' => $request->user()?->id,
            'reason' => $validated['reason'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Override added.')]);

        return back();
    }

    public function update(
        FeatureFlagOverrideUpdateRequest $request,
        FeatureFlag $featureFlag,
        FeatureFlagOverride $override,
    ): RedirectResponse {
        abort_unless($override->feature_flag_id === $featureFlag->id, 404);

        $validated = $request->validated();

        $override->forceFill([
            'enabled' => (bool) $validated['enabled'],
            'expires_at' => $validated['expires_at'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Override updated.')]);

        return back();
    }

    public function destroy(
        FeatureFlagOverrideDestroyRequest $request,
        FeatureFlag $featureFlag,
        FeatureFlagOverride $override,
    ): RedirectResponse {
        unset($request);
        abort_unless($override->feature_flag_id === $featureFlag->id, 404);

        $override->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Override removed.')]);

        return back();
    }
}
