<?php
namespace App\Http\Controllers;
use App\Actions\Inspections\CreateInspection; use App\Http\Requests\Inspections\{StoreInspectionRequest,UpdatePlannedInspectionRequest}; use App\Models\Inspection; use Illuminate\Http\{JsonResponse,RedirectResponse,Request};
final class InspectionController extends Controller {
 public function index(Request $r):JsonResponse{$this->authorize('viewAny',Inspection::class);return response()->json(Inspection::query()->forOrganization((int)$r->user()->organization_id)->latest()->paginate());}
 public function create(Request $r):JsonResponse{$this->authorize('create',Inspection::class);return response()->json(['store_url'=>route('inspections.store')]);}
 public function store(StoreInspectionRequest $r,CreateInspection $a):RedirectResponse{$this->authorize('create',Inspection::class);$i=$a->handle((int)$r->user()->organization_id,(int)$r->user()->id,$r->validated());return redirect()->route('inspections.show',$i);}
 public function show(Request $r,Inspection $inspection):JsonResponse{$i=$this->tenant($r,$inspection);$this->authorize('view',$i);return response()->json($i->load(['responsibles','referenceDocuments','statusHistories']));}
 public function edit(Request $r,Inspection $inspection):JsonResponse{$i=$this->tenant($r,$inspection);$this->authorize('updatePlanned',$i);return response()->json($i);}
 public function update(UpdatePlannedInspectionRequest $r,Inspection $inspection):RedirectResponse{$i=$this->tenant($r,$inspection);$this->authorize('updatePlanned',$i);$i->update([...$r->validated(),'updated_by'=>$r->user()->id]);return redirect()->route('inspections.show',$i);}
 private function tenant(Request $r,Inspection $i):Inspection{abort_unless((int)$i->organization_id===(int)$r->user()->organization_id,404);return $i;}
}
