<?php
namespace App\Models;
use App\Enums\InspectionResponsibility;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
final class InspectionResponsible extends Model { use BelongsToOrganization; protected $guarded=[]; protected function casts():array{return ['responsibility'=>InspectionResponsibility::class,'is_primary'=>'boolean','assigned_at'=>'datetime'];} }
