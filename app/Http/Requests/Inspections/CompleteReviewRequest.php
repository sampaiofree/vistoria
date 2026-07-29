<?php
namespace App\Http\Requests\Inspections;
final class CompleteReviewRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('completeReview');}public function rules():array{return ['notes'=>['nullable','string','max:5000']];}}
