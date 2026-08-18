<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PhotoProcessingStatus;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionOverviewPhoto> */
final class InspectionOverviewPhotoFactory extends Factory
{
    protected $model = InspectionOverviewPhoto::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'inspection_id' => null,
            'inspection_overview_block_id' => null,
            'slot' => 1,
            'processing_status' => PhotoProcessingStatus::Pending,
            'disk' => 'inspection_photos',
            'original_path' => 'overview/original.jpg',
            'optimized_path' => null,
            'thumbnail_path' => null,
            'original_name' => 'vista-geral.jpg',
            'original_mime_type' => 'image/jpeg',
            'original_extension' => 'jpg',
            'original_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => null,
        ];
    }

    public function forBlock(InspectionOverviewBlock $block, int $slot = 1): static
    {
        return $this->state([
            'organization_id' => $block->organization_id,
            'inspection_id' => $block->inspection_id,
            'inspection_overview_block_id' => $block->id,
            'slot' => $slot,
        ]);
    }

    public function ready(): static
    {
        return $this->state([
            'processing_status' => PhotoProcessingStatus::Ready,
            'optimized_path' => 'overview/optimized.webp',
            'thumbnail_path' => 'overview/thumbnail.webp',
            'processed_at' => now(),
        ]);
    }
}
