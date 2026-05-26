<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantSwitchController extends Controller
{
    public function __invoke(Request $request, string $tenant): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $model = Tenant::query()->where('slug', $tenant)->first();
        abort_if($model === null, 404);
        abort_unless($user->tenants()->whereKey($model->id)->exists(), 403);

        $user->forceFill(['current_tenant_id' => $model->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Switched tenant.')]);

        return to_route('tenants.dashboard', ['tenantSlug' => $model->slug]);
    }
}
