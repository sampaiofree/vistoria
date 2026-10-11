<?php

namespace App\Models;

use App\Enums\OperationalRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserOperationalRole extends Model
{
    protected $fillable = ['role'];

    protected function casts(): array
    {
        return ['role' => OperationalRole::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
