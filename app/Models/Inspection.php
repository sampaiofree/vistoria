<?php
namespace App\Models;
use App\Enums\{InspectionResponsibility,InspectionStatus,InspectionType};
use App\Models\Concerns\{BelongsToOrganization,HasPublicId};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class Inspection extends Model {
 use BelongsToOrganization, HasPublicId;
 protected $guarded=[];
 protected function casts():array{return ['inspection_type'=>InspectionType::class,'status'=>InspectionStatus::class,'context_snapshot'=>'array','scheduled_for'=>'date','started_at'=>'datetime','field_completed_at'=>'datetime','reviewed_at'=>'datetime','approved_at'=>'datetime','released_at'=>'datetime','canceled_at'=>'datetime'];}
 public function responsibles():HasMany{return $this->hasMany(InspectionResponsible::class);}
 public function referenceDocuments():HasMany{return $this->hasMany(InspectionReferenceDocument::class);}
 public function statusHistories():HasMany{return $this->hasMany(InspectionStatusHistory::class);}
 public function hasResponsibility(InspectionResponsibility $role,?int $userId=null):bool{return $this->responsibles()->where('responsibility',$role->value)->when($userId,fn($q)=>$q->where('user_id',$userId))->exists();}
}
