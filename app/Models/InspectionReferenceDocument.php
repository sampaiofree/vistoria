<?php
namespace App\Models;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
final class InspectionReferenceDocument extends Model { use BelongsToOrganization; public const UPDATED_AT=null; protected $guarded=[]; }
