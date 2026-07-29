<?php
namespace App\Policies;
use App\Enums\{InspectionResponsibility as Role,InspectionStatus as Status};
use App\Models\{Inspection,User};
final class InspectionPolicy {
 public function viewAny(User $user):bool{return $this->tenantUser($user);}
 public function view(User $user,Inspection $inspection):bool{return $this->sameTenant($user,$inspection);}
 public function create(User $user):bool{return $this->tenantUser($user)&&$user->isCompanyAdmin();}
 public function updatePlanned(User $user,Inspection $inspection):bool{return $this->adminOf($user,$inspection)&&$inspection->status===Status::Planned;}
 public function assignResponsibles(User $user,Inspection $inspection):bool{return $this->adminOf($user,$inspection)&&!in_array($inspection->status,[Status::Released,Status::Canceled],true);}
 public function manageReferences(User $user,Inspection $inspection):bool{return $this->adminOf($user,$inspection)&&in_array($inspection->status,[Status::Planned,Status::InProgress,Status::InCorrection],true);}
 public function start(User $user,Inspection $inspection):bool{return $this->responsible($user,$inspection,Role::Inspector)&&$inspection->status===Status::Planned;}
 public function submitForReview(User $user,Inspection $inspection):bool{return $this->responsible($user,$inspection,Role::Preparer)&&in_array($inspection->status,[Status::InProgress,Status::InCorrection],true);}
 public function returnForCorrection(User $user,Inspection $inspection):bool{return $this->sameTenant($user,$inspection) && (($inspection->status===Status::AwaitingReview && $this->assigned($user,$inspection,Role::Reviewer)) || ($inspection->status===Status::AwaitingApproval && $this->assigned($user,$inspection,Role::Approver)));}
 public function completeReview(User $user,Inspection $inspection):bool{return $this->responsible($user,$inspection,Role::Reviewer)&&$inspection->status===Status::AwaitingReview;}
 public function approve(User $user,Inspection $inspection):bool{return $this->responsible($user,$inspection,Role::Approver)&&$inspection->status===Status::AwaitingApproval;}
 public function release(User $user,Inspection $inspection):bool{return $this->responsible($user,$inspection,Role::Releaser)&&in_array($inspection->status,[Status::Approved,Status::ReportGenerated],true);}
 public function cancel(User $user,Inspection $inspection):bool{return $this->adminOf($user,$inspection)&&!in_array($inspection->status,[Status::Released,Status::Canceled],true);}
 private function tenantUser(User $u):bool{return $u->isActive()&&!$u->isSuperAdmin()&&$u->organization_id!==null;}
 private function sameTenant(User $u,Inspection $i):bool{return $this->tenantUser($u)&&$i->belongsToOrganization((int)$u->organization_id);}
 private function adminOf(User $u,Inspection $i):bool{return $u->isCompanyAdmin()&&$this->sameTenant($u,$i);}
 private function assigned(User $u,Inspection $i,Role $r):bool{return $i->hasResponsibility($r,(int)$u->id);}
 private function responsible(User $u,Inspection $i,Role $r):bool{return $this->sameTenant($u,$i)&&$this->assigned($u,$i,$r);}
}
