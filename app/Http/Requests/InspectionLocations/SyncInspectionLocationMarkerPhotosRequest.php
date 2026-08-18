<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\InspectionLocationMarker;
use Illuminate\Foundation\Http\FormRequest;

final class SyncInspectionLocationMarkerPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marker = $this->route('marker');

        return $marker instanceof InspectionLocationMarker && ($this->user()?->can('update', $marker) ?? false);
    }

    public function rules(): array
    {
        return [
            'photo_ids' => ['present', 'array', 'max:100'],
            'photo_ids.*' => ['required', 'string', 'distinct'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'map_lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
