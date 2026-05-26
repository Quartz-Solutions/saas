<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * Auto-records create/update/delete/restore events to `audit_logs`,
 * including a diff (old + new values) on updates. Models opt in via
 * `Model::observe(AuditObserver::class)` in AppServiceProvider.
 *
 * Auditable fields can be tightened by setting a public static
 * `$auditableFields = ['name', 'email']` on the model. When unset, all
 * dirty attributes (except `password`, `remember_token`, the two-factor
 * blobs) are written.
 */
class AuditObserver
{
    /**
     * Attributes that are never recorded — secrets / noise.
     *
     * @var array<int, string>
     */
    protected array $globalSkip = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'updated_at',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $this->filter($model, $model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $oldFiltered = $this->filter($model, $old);
        $newFiltered = $this->filter($model, $changes);

        if ($oldFiltered === [] && $newFiltered === []) {
            return;
        }

        $this->record($model, 'updated', $oldFiltered, $newFiltered);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $this->filter($model, $model->getOriginal()), null);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', null, $this->filter($model, $model->getAttributes()));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function record(Model $model, string $action, ?array $old, ?array $new): void
    {
        try {
            $request = RequestFacade::instance();

            AuditLog::query()->create([
                'tenant_id' => $this->resolveTenantId($model),
                'user_id' => Auth::id(),
                'action' => $action,
                'auditable_type' => $model::class,
                'auditable_id' => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip' => optional($request)->ip(),
                'user_agent' => Str::limit((string) optional($request)->userAgent(), 500, ''),
            ]);
        } catch (Throwable $e) {
            // Audit writes must never break the underlying transaction.
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function filter(Model $model, array $attrs): array
    {
        $allow = $this->allowList($model);

        $result = [];
        foreach ($attrs as $key => $value) {
            if (in_array($key, $this->globalSkip, true)) {
                continue;
            }
            if ($allow !== null && ! in_array($key, $allow, true)) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array<int, string>|null
     */
    protected function allowList(Model $model): ?array
    {
        $class = $model::class;
        if (property_exists($class, 'auditableFields')) {
            /** @var array<int, string> $fields */
            $fields = $class::$auditableFields;

            return $fields;
        }

        return null;
    }

    protected function resolveTenantId(Model $model): ?int
    {
        if (isset($model->tenant_id)) {
            return (int) $model->tenant_id;
        }

        if (method_exists($model, 'getKey') && $model::class === Tenant::class) {
            return (int) $model->getKey();
        }

        return null;
    }
}
