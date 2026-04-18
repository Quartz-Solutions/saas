# Model Conventions

Follow `app/Models/User.php` as the reference implementation.

## PHP 8 attributes (Laravel 13 style)
Use attributes for `Fillable` and `Hidden` — not `$fillable` / `$hidden` properties.
```php
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable { /* ... */ }
```

## Mass assignment
- Every model declares `#[Fillable([...])]` with the **exact** allowed columns
- Default-deny: anything not in `Fillable` must be set with `$model->forceFill(...)` or per-attribute assignment
- Never use `Model::unguard()` or `#[Guarded([])]`

## Casts via method
```php
protected function casts(): array {
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'price_cents' => 'integer',
        'is_active' => 'boolean',
    ];
}
```
- Use `'hashed'` cast for password fields (never hash in actions)
- Cast money columns to `'integer'` (see backend/migration-conventions.md)

## Query scopes & methods on the model
Keep domain logic on the model. No repositories. Use `#[Scope]` attribute for scopes:
```php
#[Scope]
protected function active(Builder $query): void {
    $query->whereNull('archived_at');
}

public function totalCents(): int {
    return $this->items->sum('subtotal_cents');
}
```

## Standard traits
- `HasFactory` — always (with `/** @use HasFactory<XFactory> */` doc for IDE)
- `SoftDeletes` — on `Order`, `Customer`, `Product` (see backend/migration-conventions.md)
- `Notifiable` — on entities that receive notifications (User, Customer)

## Factory binding
Reference factories via `Database\Factories\<Model>Factory` import + the `#[\Illuminate\Database\Eloquent\Factories\Factory(UserFactory::class)]` attribute (or rely on naming convention).
