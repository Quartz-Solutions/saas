<?php

namespace App\Http\Requests\Settings\Privacy;

use App\Models\DataExportRequest as DataExportRequestModel;
use Illuminate\Foundation\Http\FormRequest;

class DataExportShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $export = $this->route('export');

        if ($user === null || ! $export instanceof DataExportRequestModel) {
            return false;
        }

        return (int) $export->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
