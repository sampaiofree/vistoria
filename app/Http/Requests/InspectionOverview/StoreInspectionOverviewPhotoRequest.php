<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionOverview;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class StoreInspectionOverviewPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageReportOverview', $inspection) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
        ];
    }
}
