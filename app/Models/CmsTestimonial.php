<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'quote', 'author_name', 'author_role', 'company',
    'avatar_url', 'logo_url', 'rating', 'is_active', 'sort_order',
])]
class CmsTestimonial extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rating' => 'integer'];
    }
}
