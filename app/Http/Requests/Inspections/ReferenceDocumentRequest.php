<?php
namespace App\Http\Requests\Inspections;
final class ReferenceDocumentRequest extends TenantInspectionRequest {public function authorize():bool{return $this->tenantAuthorized('manageReferences');}public function rules():array{return ['equipment_document_id'=>['required','integer','min:1']];}}
