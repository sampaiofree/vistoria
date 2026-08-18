<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\InspectionLocationMap;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionLocationMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $map = $this->route('map');

        return $map instanceof InspectionLocationMap
            && ($this->user()?->can('update', $map) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'position' => ['sometimes', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
