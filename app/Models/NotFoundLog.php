<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['path', 'hits', 'last_hit_at', 'referer'])]
class NotFoundLog extends Model
{
    use HasFactory;

    protected $table = 'cms_not_found_log';

    protected function casts(): array
    {
        return [
            'last_hit_at' => 'datetime',
            'hits' => 'integer',
        ];
    }
}
