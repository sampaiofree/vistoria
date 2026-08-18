<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionOverview;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionOverviewBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageReportOverview', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment' => TextNormalizer::nullableText($this->input('comment')),
            'recommendation' => TextNormalizer::nullableText($this->input('recommendation')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'comment' => ['present', 'nullable', 'string', 'max:600'],
            'recommendation' => ['present', 'nullable', 'string', 'max:600'],
        ];
    }
}
