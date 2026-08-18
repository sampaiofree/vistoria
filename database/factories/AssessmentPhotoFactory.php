<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssessmentPhoto> */
final class AssessmentPhotoFactory extends Factory
{
    protected $model = AssessmentPhoto::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inspection_id' => Inspection::factory(),
            'defect_assessment_id' => DefectAssessment::factory(),
            'photo_type' => AssessmentPhotoType::Detail,
            'caption' => fake()->sentence(),
            'position' => 1,
            'processing_status' => PhotoProcessingStatus::Pending,
            'disk' => 'inspection_photos',
            'original_name' => 'photo.jpg',
            'original_mime_type' => 'image/jpeg',
            'original_extension' => 'jpg',
            'original_size' => 1024,
            'uploaded_at' => now(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => ['processing_status' => PhotoProcessingStatus::Ready]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['processing_status' => PhotoProcessingStatus::Failed, 'processing_error' => 'Falha simulada.']);
    }
}
