<?php
namespace App\Http\Requests\Inspections;
final class SubmitForReviewRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('submitForReview');}public function rules():array{return ['notes'=>['nullable','string','max:5000']];}}
