<?php
namespace App\Http\Requests\Inspections;
use App\Models\Inspection; use Illuminate\Foundation\Http\FormRequest;
abstract class TenantInspectionRequest extends FormRequest {public function authorize():bool{return $this->tenantAuthorized();}protected function tenantAuthorized(?string $ability=null):bool{$u=$this->user();$i=$this->route('inspection');if($u===null||$u->isSuperAdmin()||!$u->isActive()||$u->organization_id===null)return false;if($i instanceof Inspection&&(int)$i->organization_id!==(int)$u->organization_id)abort(404);return $ability===null||$u->can($ability,$i);}}
