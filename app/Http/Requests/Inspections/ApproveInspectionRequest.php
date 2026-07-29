<?php
namespace App\Http\Requests\Inspections;
final class ApproveInspectionRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('approve');}public function rules():array{return ['notes'=>['nullable','string','max:5000']];}}
