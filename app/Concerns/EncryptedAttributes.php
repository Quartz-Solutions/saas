<?php

namespace App\Concerns;

/**
 * Opt-in scaffold for column-level encryption of PII fields.
 *
 * Apply per project rather than across the boilerplate — the cost is
 * (a) any column you list becomes opaque to SQL (`WHERE email = ...`
 * stops working without a separate searchable hash column) and (b)
 * losing APP_KEY locks you out of the data. Make the decision per
 * tenant/per project.
 *
 * Usage:
 *
 * ```php
 * use App\Concerns\EncryptedAttributes;
 *
 * class CustomerProfile extends Model
 * {
 *     use EncryptedAttributes;
 *
 *     // Adds `email`, `phone` to the cast list as `encrypted` automatically.
 *     protected array $encryptedAttributes = ['email', 'phone'];
 * }
 * ```
 *
 * Notes:
 *  - Uses Laravel's built-in `encrypted` cast (AES-256-CBC).
 *  - Mass-assignment-friendly — Laravel handles the encryption on save
 *    and decryption on read, transparent to callers.
 *  - If you need search, store a `*_hash` SHA-256 column alongside the
 *    encrypted one and query by the hash. See
 *    `agent-os/standards/backend/migration-conventions.md` for the
 *    pattern.
 */
trait EncryptedAttributes
{
    /**
     * Laravel calls this on every model instance to assemble the cast
     * list. We merge the `$encryptedAttributes` declared on the model
     * into the parent's casts so consumers don't have to remember.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        /** @var array<string, string> $parent */
        $parent = method_exists(parent::class, 'casts') ? parent::casts() : [];

        $encrypted = property_exists($this, 'encryptedAttributes') ? $this->encryptedAttributes : [];

        foreach ($encrypted as $field) {
            $parent[$field] = 'encrypted';
        }

        return $parent;
    }
}
