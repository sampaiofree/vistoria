<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
final class InspectionStatusHistory extends Model { use BelongsToOrganization; public const UPDATED_AT=null; protected $guarded=[]; protected function casts():array{return ['metadata'=>'array'];} }
