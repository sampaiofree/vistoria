<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'organization_id', // Organização proprietária; sem coluna correspondente na planilha
        'client_id', // Cliente proprietário; sem coluna correspondente na planilha
        'numero_cliente', // Número de identificação do cliente
        'numero_interno', // Número de identificação interno
        'maintenance_plan_code', // Plano de manutenção
        'maintenance_item_code', // Item manutenção
        'tag', // Campo de ordenação (TAG)
        'normalized_tag', // Derivado de Campo de ordenação (TAG), normalizado para busca; sem coluna correspondente na planilha
        'defect_code_prefix', // Prefixo de avaria
        'name', // Denominação do loc.instalação
        'description', // Descrição item de manutenção
        'manufacturer', // Fabricante; sem coluna correspondente na planilha
        'model', // Modelo; sem coluna correspondente na planilha
        'serial_number', // Número de série; sem coluna correspondente na planilha
        'asset_code', // Código patrimonial; sem coluna correspondente na planilha
        'abc_code', // Código ABC
        'installation_location', // Local de instalação
        'area_code', // Area(usina)
        'area_name', // Area.nome
        'subarea_code', // Sub-area
        'subarea_name', // sub-area.nome
        'task_list_group', // GrpLisTar.
        'task_list_group_counter', // Numerador de grupos
        'commissioned_at', // Data de entrada em operação; sem coluna correspondente na planilha
        'status', // Estado do cadastro; sem coluna correspondente na planilha
        'notes', // Observações; sem coluna correspondente na planilha
        'decommissioned_at', // Data da baixa; sem coluna correspondente na planilha
        'decommissioned_by', // Responsável pela baixa; sem coluna correspondente na planilha
        'decommission_reason', // Motivo da baixa; sem coluna correspondente na planilha
        'created_by', // Responsável pelo cadastro; sem coluna correspondente na planilha
        'updated_by', // Responsável pela última edição; sem coluna correspondente na planilha
    ];

    protected function casts(): array
    {
        return [
            'commissioned_at' => 'date',
            'decommissioned_at' => 'datetime',
            'status' => EquipmentStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function decommissioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decommissioned_by');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)
            ->orderByDesc('created_at');
    }

    public function defectLocationMaps(): HasMany
    {
        return $this->hasMany(DefectLocationMap::class)->orderBy('id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class)
            ->orderByDesc('created_at');
    }

    public function releasedInspections(): HasMany
    {
        return $this->inspections()
            ->where('status', InspectionStatus::Released->value);
    }

    public function isActive(): bool
    {
        return $this->status === EquipmentStatus::Active;
    }

    public function hasOperationalStructure(): bool
    {
        return $this->client?->isActive() === true;
    }

    public function canReceiveInspection(): bool
    {
        return $this->isActive()
            && $this->hasOperationalStructure();
    }
}
