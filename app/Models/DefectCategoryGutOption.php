<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GutCriterion;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DefectCategoryGutOption extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'defect_category_id',
        'criterion',
        'score',
        'color',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'criterion' => GutCriterion::class,
            'score' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DefectCategory::class, 'defect_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
