<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\InspectionLocationMarker;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionLocationMarkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $marker = $this->route('marker');

        return $marker instanceof InspectionLocationMarker && ($this->user()?->can('update', $marker) ?? false);
    }

    public function rules(): array
    {
        return [
            'defect_assessment_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:240'],
            'geometry' => ['required', 'array'],
            'style' => ['nullable', 'array'],
            'position' => ['required', 'integer', 'min:1', 'max:'.config('inspection_locations.limits.markers_per_map')],
            'lock_version' => ['required', 'integer', 'min:1'],
            'map_lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
