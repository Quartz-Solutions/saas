# Migration Conventions

Project-specific schema conventions for the e-commerce + POS domain.

## Money
Store monetary values as integer minor units (cents/fils). Never use float; never use decimal in this app.

```php
$table->unsignedBigInteger('price_cents');         // unit price
$table->unsignedBigInteger('total_cents');         // line/order total
$table->string('currency', 3);                     // ISO 4217 (e.g. 'USD', 'SAR')
```
- Suffix money columns with `_cents` to make the unit explicit
- Always pair with a `currency` column when totals can mix currencies
- Never store tax/discount as a float — store derived `_cents` columns
- Conversion to display happens in PHP/JS, not in SQL

## Foreign keys
No blanket cascade default — pick per relationship and **state the choice in the migration**.
```php
$table->foreignId('order_id')->constrained()->cascadeOnDelete();    // line items die with order
$table->foreignId('product_id')->constrained()->restrictOnDelete(); // can't delete a product that's been sold
$table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // walk-in sales OK
```
- Always include `->constrained()` (or `->references()->on()`) — never skip the FK constraint
- Always include explicit cascade behavior; don't rely on DB defaults

## Soft deletes
Use `SoftDeletes` for entities with audit / history value:
- `orders`, `customers`, `products` (and their variants)
- Add `$table->softDeletes();` to the migration; add `use SoftDeletes;` on the model
- Other entities (line items, sessions, settings) hard-delete

## Standard column patterns
- `$table->id();` (autoincrement bigint primary key)
- `$table->timestamps();` on every business table
- Column add to existing table: `$table->after('column')->nullable()` to avoid migration failures on populated tables
- Multiple related tables → one migration (e.g. `cache` + `cache_locks`)
- Always implement `down()` with `Schema::dropIfExists`
- Use anonymous migration class: `return new class extends Migration { ... };`
