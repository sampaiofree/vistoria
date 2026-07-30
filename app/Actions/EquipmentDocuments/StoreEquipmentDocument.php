<?php

namespace App\Actions\EquipmentDocuments;

use App\Enums\DocumentStatus;
use App\Enums\EquipmentDocumentType;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StoreEquipmentDocument
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(
        User $actor,
        Equipment $equipment,
        UploadedFile $file,
        array $data,
    ): EquipmentDocument {
        $equipment = Equipment::query()
            ->forOrganization($this->tenant->id())
            ->whereKey($equipment->getKey())
            ->firstOrFail();

        $documentGroup = $this->normalizeDocumentGroup($data['document_group'] ?? null);

        $groupOwner = EquipmentDocument::query()
            ->forOrganization($this->tenant->id())
            ->where('document_group', $documentGroup)
            ->value('equipment_id');

        if ($groupOwner !== null && (int) $groupOwner !== $equipment->getKey()) {
            throw ValidationException::withMessages([
                'document_group' => 'O grupo informado pertence a outro equipamento.',
            ]);
        }

        $documentPublicId = (string) Str::ulid();
        $extension = $this->resolveExtension($file);
        $disk = 'equipment_documents';
        $directory = sprintf(
            'organizations/%d/equipments/%s/documents/%s',
            $equipment->organization_id,
            $equipment->public_id,
            $documentPublicId,
        );
        $filename = $documentPublicId.($extension !== '' ? '.'.$extension : '');
        $path = $directory.'/'.$filename;

        try {
            return DB::transaction(function () use (
                $actor,
                $equipment,
                $file,
                $data,
                $documentGroup,
                $documentPublicId,
                $disk,
                $directory,
                $filename,
                $path,
            ): EquipmentDocument {
                $equipment = Equipment::query()
                    ->forOrganization($this->tenant->id())
                    ->lockForUpdate()
                    ->with(['client', 'unit', 'area', 'subarea'])
                    ->findOrFail($equipment->getKey());

                $currentDocuments = EquipmentDocument::query()
                    ->forOrganization($this->tenant->id())
                    ->where('equipment_id', $equipment->getKey())
                    ->where('document_group', $documentGroup)
                    ->lockForUpdate()
                    ->get();

                if (! Storage::disk($disk)->putFileAs($directory, $file, $filename)) {
                    throw new RuntimeException('Nao foi possivel salvar o arquivo do documento.');
                }

                $currentDocuments->each(function (EquipmentDocument $document): void {
                    $document->update([
                        'is_current' => false,
                    ]);
                });

                return EquipmentDocument::query()->create([
                    'public_id' => $documentPublicId,
                    'organization_id' => $equipment->organization_id,
                    'equipment_id' => $equipment->id,
                    'document_group' => $documentGroup,
                    'document_type' => EquipmentDocumentType::from($data['document_type']),
                    'title' => $data['title'],
                    'document_number' => $data['document_number'] ?? null,
                    'revision' => $data['revision'] ?? null,
                    'description' => $data['description'] ?? null,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream',
                    'extension' => $this->resolveExtension($file) ?: null,
                    'size' => $file->getSize() ?? 0,
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                    'is_current' => true,
                    'status' => DocumentStatus::Active,
                    'uploaded_by' => $actor->id,
                    'issued_at' => $data['issued_at'] ?? null,
                ]);
            });
        } catch (Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }
    }

    private function normalizeDocumentGroup(?string $documentGroup): string
    {
        $normalized = is_string($documentGroup)
            ? mb_strtoupper(trim($documentGroup))
            : '';

        return $normalized !== ''
            ? $normalized
            : (string) Str::ulid();
    }

    private function resolveExtension(UploadedFile $file): string
    {
        $extension = $file->extension() ?: $file->getClientOriginalExtension();

        return is_string($extension) ? mb_strtolower($extension) : '';
    }
}
