<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'locale', 'payload', 'updated_by_id'])]
class CmsGlobal extends Model
{
    use HasFactory;

    protected $table = 'cms_globals';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
