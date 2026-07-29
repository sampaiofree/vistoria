<?php
namespace App\Actions\Inspections; use App\Models\{Inspection,InspectionReferenceDocument};
final class SyncInspectionReferences {public function add(Inspection $i,int $actor,int $id):InspectionReferenceDocument{return $i->referenceDocuments()->firstOrCreate(['equipment_document_id'=>$id],['organization_id'=>$i->organization_id,'added_by'=>$actor,'created_at'=>now()]);}public function remove(Inspection $i,InspectionReferenceDocument $r):void{abort_unless((int)$r->inspection_id===(int)$i->id&&$r->belongsToOrganization((int)$i->organization_id),404);$r->delete();}}
