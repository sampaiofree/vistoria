<?php
namespace App\Http\Requests\Inspections;
final class UpdatePlannedInspectionRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('updatePlanned');}public function rules():array{return ['service_order'=>['nullable','string','max:100'],'external_report_number'=>['nullable','string','max:150'],'procedure_number'=>['nullable','string','max:150'],'atmospheric_classification'=>['nullable','string','max:50'],'scheduled_for'=>['nullable','date'],'general_notes'=>['nullable','string','max:5000']];}}
