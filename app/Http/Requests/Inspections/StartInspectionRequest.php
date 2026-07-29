<?php
namespace App\Http\Requests\Inspections;
final class StartInspectionRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('start');}public function rules():array{return [];}}
