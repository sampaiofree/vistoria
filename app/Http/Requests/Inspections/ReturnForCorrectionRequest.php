<?php
namespace App\Http\Requests\Inspections;
final class ReturnForCorrectionRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('returnForCorrection');}public function rules():array{return ['reason'=>['required','string','min:10','max:5000']];}}
