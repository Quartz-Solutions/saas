# Migration Conventions

## Money
Store monetary values as integer minor units (cents/fils). Never use float; never use decimal in this app.

```php
$table->unsignedBigInteger('amount_cents');        // any monetary amount
$table->unsignedBigInteger('total_cents');         // line/aggregate total
$table->string('currency', 3);                     // ISO 4217 (e.g. 'USD', 'SAR')
```
- Suffix money columns with `_cents` to make the unit explicit
- Always pair with a `currency` column when totals can mix currencies
- Never store tax/discount as a float — store derived `_cents` columns
- Conversion to display happens in PHP/JS, not in SQL

## Foreign keys
No blanket cascade default — pick per relationship and **state the choice in the migration**.
```php
$table->foreignId('parent_id')->constrained()->cascadeOnDelete();    // children die with parent
$table->foreignId('related_id')->constrained()->restrictOnDelete();  // can't delete if referenced
$table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete(); // optional relation
```
- Always include `->constrained()` (or `->references()->on()`) — never skip the FK constraint
- Always include explicit cascade behavior; don't rely on DB defaults

## Soft deletes
Use `SoftDeletes` for entities with audit / history value (anything user-visible that has financial, compliance, or recovery implications).
- Add `$table->softDeletes();` to the migration; add `use SoftDeletes;` on the model
- Operational/ephemeral entities (line items, sessions, settings, cache) hard-delete

## Standard column patterns
- `$table->id();` (autoincrement bigint primary key)
- `$table->timestamps();` on every business table
- Column add to existing table: `$table->after('column')->nullable()` to avoid migration failures on populated tables
- Multiple related tables → one migration when their lifecycle is coupled (e.g. `cache` + `cache_locks`)
- Always implement `down()` with `Schema::dropIfExists`
- Use anonymous migration class: `return new class extends Migration { ... };`

## Append-only migrations
Once a migration has run against any live database, **never edit it** — write a new migration. Editing a shipped migration breaks existing environments while passing fresh-DB tests.
