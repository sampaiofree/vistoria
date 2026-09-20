<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionReportRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('updateReportRevision', $inspection) ?? false);
    }

    public function rules(): array
    {
        return [
            'report_revision' => ['required', 'integer', 'min:0'],
        ];
    }
}
