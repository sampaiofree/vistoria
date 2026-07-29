<?php
namespace App\Http\Requests\Inspections;
final class ReleaseInspectionRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('release');}public function rules():array{return ['notes'=>['nullable','string','max:5000']];}}
