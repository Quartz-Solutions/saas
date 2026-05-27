<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cms_form_id', 'payload', 'ip', 'user_agent', 'referer'])]
class CmsFormSubmission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CmsForm::class, 'cms_form_id');
    }
}
