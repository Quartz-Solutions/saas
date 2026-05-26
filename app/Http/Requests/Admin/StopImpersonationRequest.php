<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\ImpersonationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stopping impersonation must be available to whoever is currently *logged
 * in as* the impersonated user — i.e. the target, not the Super Admin.
 * We therefore can't use `AdminFormRequest` here; we just require an active
 * impersonation session.
 */
class StopImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ImpersonationService::class)->isImpersonating();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
