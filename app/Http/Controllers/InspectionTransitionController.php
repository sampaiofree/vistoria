<?php
namespace App\Http\Controllers;
use App\Actions\Inspections\TransitionInspection;use App\Enums\InspectionStatus as S;use App\Http\Requests\Inspections\{ApproveInspectionRequest,CancelInspectionRequest,CompleteReviewRequest,ReleaseInspectionRequest,ReturnForCorrectionRequest,StartInspectionRequest,SubmitForReviewRequest,TenantInspectionRequest};use App\Models\Inspection;use Illuminate\Http\RedirectResponse;
final class InspectionTransitionController extends Controller {
 public function start(StartInspectionRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'start',S::InProgress);}
 public function submitForReview(SubmitForReviewRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'submitForReview',S::AwaitingReview);}
 public function returnForCorrection(ReturnForCorrectionRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'returnForCorrection',S::InCorrection,$r->validated('reason'));}
 public function completeReview(CompleteReviewRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'completeReview',S::AwaitingApproval);}
 public function approve(ApproveInspectionRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'approve',S::Approved);}
 public function release(ReleaseInspectionRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'release',S::Released);}
 public function cancel(CancelInspectionRequest $r,Inspection $inspection,TransitionInspection $a):RedirectResponse{return $this->run($r,$inspection,$a,'cancel',S::Canceled,$r->validated('reason'));}
 private function run(TenantInspectionRequest $r,Inspection $i,TransitionInspection $a,string $ability,S $to,?string $reason=null):RedirectResponse{abort_unless((int)$i->organization_id===(int)$r->user()->organization_id,404);$this->authorize($ability,$i);$a->handle($i,(int)$r->user()->id,$to,$reason);return redirect()->route('inspections.show',$i);}
}
