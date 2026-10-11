<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SelfAssignInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection && ($this->user()?->can('selfAssign', $inspection) ?? false);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['missing'],
            'responsibility' => ['missing'],
            'role' => ['missing'],
            'responsibilities' => ['sometimes', 'required', 'array', 'min:1', 'max:2'],
            'responsibilities.*' => ['distinct', Rule::in(['approver', 'releaser'])],
        ];
    }
}
