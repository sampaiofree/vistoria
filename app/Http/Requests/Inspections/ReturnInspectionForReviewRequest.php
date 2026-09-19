<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

final class ReturnInspectionForReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection && ($this->user()?->can('returnForReview', $inspection) ?? false);
    }

    public function rules(): array
    {
        return ['justification' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
