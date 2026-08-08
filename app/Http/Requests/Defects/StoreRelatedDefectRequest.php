<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectRelationType;
use App\Models\Defect;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRelatedDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');
        $defect = $this->route('defect');

        return $inspection instanceof Inspection
            && $defect instanceof Defect
            && ($this->user()?->can('createRelated', [Defect::class, $inspection, $defect]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'relation_type' => strtolower(trim((string) $this->input('relation_type'))),
            'title' => TextNormalizer::text((string) $this->input('title')),
            'origin_description' => TextNormalizer::nullableText($this->input('origin_description')),
            'location_description' => TextNormalizer::nullableText($this->input('location_description')),
            'comment' => TextNormalizer::nullableText($this->input('comment')),
            'recommendation' => TextNormalizer::nullableText($this->input('recommendation')),
        ]);
    }

    public function rules(): array
    {
        return [
            'relation_type' => ['required', Rule::enum(DefectRelationType::class)],
            'title' => ['required', 'string', 'max:200'],
            'origin_description' => ['nullable', 'string', 'max:10000'],
            'location_description' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'recommendation' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
