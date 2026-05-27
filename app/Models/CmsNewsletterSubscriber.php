<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email', 'locale', 'source', 'provider', 'provider_id',
    'confirmed_at', 'unsubscribed_at', 'ip',
])]
class CmsNewsletterSubscriber extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }
}
