<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class ApproveInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('approve', $inspection) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
