<?php
namespace App\Http\Requests\Inspections;
use App\Enums\InspectionResponsibility; use Illuminate\Validation\Rule;
final class AssignResponsibleRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('assignResponsibles');}public function rules():array{return ['user_id'=>['required','integer',Rule::exists('users','id')->where('organization_id',$this->user()?->organization_id)],'responsibility'=>['required',Rule::enum(InspectionResponsibility::class)],'is_primary'=>['sometimes','boolean']];}}
