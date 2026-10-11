<?php

namespace App\Console\Commands;

use App\Enums\InspectionResponsibility;
use App\Enums\OperationalRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditOperationalRoles extends Command
{
    protected $signature = 'app:audit-operational-roles';

    protected $description = 'Identifica contas internas sem função e responsabilidades abertas sem usuário habilitado, sem alterar dados.';

    public function handle(): int
    {
        $hasRelation = Schema::hasTable('user_operational_roles');
        $hasLegacyColumn = Schema::hasColumn('users', 'operational_role');

        $missingRoles = DB::table('users')
            ->whereIn('account_type', ['member', 'company_admin'])
            ->when(
                $hasRelation,
                fn ($query) => $query->whereNotExists(fn ($roles) => $roles
                    ->selectRaw('1')
                    ->from('user_operational_roles')
                    ->whereColumn('user_operational_roles.user_id', 'users.id')),
                fn ($query) => $query->whereNull('operational_role'),
            )
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id', 'name', 'email', 'status']);

        $this->line('Contas internas sem função: '.$missingRoles->count());
        if ($missingRoles->isNotEmpty()) {
            $this->table(['Organização', 'Usuário', 'Nome', 'E-mail', 'Status'], $missingRoles
                ->map(fn ($user): array => [$user->organization_id, $user->id, $user->name, $user->email, $user->status])
                ->all());
        }

        $requiredRoles = [
            InspectionResponsibility::Preparer->value => OperationalRole::Planner->value,
            InspectionResponsibility::Reviewer->value => OperationalRole::Inspector->value,
            InspectionResponsibility::Approver->value => OperationalRole::Reviewer->value,
            InspectionResponsibility::Releaser->value => OperationalRole::Releaser->value,
        ];

        $assignments = DB::table('inspection_responsibles as ir')
            ->join('inspections as i', 'i.id', '=', 'ir.inspection_id')
            ->leftJoin('users as u', 'u.id', '=', 'ir.user_id')
            ->whereNotIn('i.status', ['released', 'canceled'])
            ->orderBy('i.organization_id')
            ->orderBy('i.id')
            ->orderBy('ir.responsibility')
            ->get([
                'ir.id', 'ir.inspection_id', 'ir.responsibility', 'ir.user_id',
                'i.organization_id', 'u.organization_id as user_organization_id',
                'u.name as user_name', 'u.status as user_status', 'u.account_type',
                ...($hasLegacyColumn ? ['u.operational_role'] : []),
            ]);

        $roleLookup = $hasRelation
            ? DB::table('user_operational_roles')
                ->whereIn('user_id', $assignments->pluck('user_id')->unique()->all())
                ->get(['user_id', 'role'])
                ->groupBy('user_id')
                ->map(fn ($roles) => $roles->pluck('role')->all())
            : collect();

        $invalid = $assignments->filter(function ($assignment) use ($hasRelation, $requiredRoles, $roleLookup): bool {
            $requiredRole = $requiredRoles[$assignment->responsibility] ?? null;
            $hasRole = $requiredRole !== null && ($hasRelation
                ? in_array($requiredRole, $roleLookup->get($assignment->user_id, []), true)
                : $assignment->operational_role === $requiredRole);

            return $assignment->user_organization_id === null
                || (int) $assignment->user_organization_id !== (int) $assignment->organization_id
                || $assignment->user_status !== 'active'
                || ! in_array($assignment->account_type, ['member', 'company_admin'], true)
                || ! $hasRole;
        });

        $this->line('Responsabilidades abertas incompatíveis: '.$invalid->count());
        if ($invalid->isNotEmpty()) {
            $this->table(['Organização', 'Inspeção', 'Responsabilidade', 'Usuário', 'Nome'], $invalid
                ->map(fn ($item): array => [
                    $item->organization_id, $item->inspection_id, $item->responsibility,
                    $item->user_id, $item->user_name ?? '—',
                ])->all());
        }

        if ($missingRoles->isNotEmpty() || $invalid->isNotEmpty()) {
            $this->error('Regularize as pendências antes de ativar funções operacionais múltiplas.');

            return self::FAILURE;
        }

        $this->info('Nenhuma pendência encontrada. Nenhum dado foi alterado.');

        return self::SUCCESS;
    }
}
