<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $inspectionIds = DB::table('inspection_responsibles')
            ->where('responsibility', 'inspector')
            ->distinct()
            ->pluck('inspection_id');

        foreach ($inspectionIds as $inspectionId) {
            DB::transaction(function () use ($inspectionId): void {
                $existingPreparers = DB::table('inspection_responsibles')
                    ->where('inspection_id', $inspectionId)
                    ->where('responsibility', 'preparer')
                    ->orderBy('id')
                    ->get();
                $existingPreparerIds = $existingPreparers->pluck('id')->all();
                $hadPreparers = $existingPreparers->isNotEmpty();

                $inspectors = DB::table('inspection_responsibles')
                    ->where('inspection_id', $inspectionId)
                    ->where('responsibility', 'inspector')
                    ->orderBy('id')
                    ->get();

                foreach ($inspectors as $inspector) {
                    $duplicate = DB::table('inspection_responsibles')
                        ->where('inspection_id', $inspectionId)
                        ->where('user_id', $inspector->user_id)
                        ->where('responsibility', 'preparer')
                        ->first();

                    if ($duplicate !== null) {
                        DB::table('inspection_responsibles')
                            ->where('id', $duplicate->id)
                            ->update([
                                'is_primary' => (bool) $duplicate->is_primary || (bool) $inspector->is_primary,
                                'assigned_at' => $this->earliestDate($duplicate->assigned_at, $inspector->assigned_at),
                                'completed_at' => $duplicate->completed_at ?? $inspector->completed_at,
                                'updated_at' => now(),
                            ]);

                        DB::table('inspection_responsibles')->where('id', $inspector->id)->delete();

                        continue;
                    }

                    DB::table('inspection_responsibles')
                        ->where('id', $inspector->id)
                        ->update([
                            'responsibility' => 'preparer',
                            'is_primary' => $hadPreparers ? false : (bool) $inspector->is_primary,
                            'updated_at' => now(),
                        ]);
                }

                $preparers = DB::table('inspection_responsibles')
                    ->where('inspection_id', $inspectionId)
                    ->where('responsibility', 'preparer')
                    ->orderBy('id')
                    ->get();

                $primary = $preparers->first(
                    fn (object $responsible): bool => in_array($responsible->id, $existingPreparerIds, true)
                        && (bool) $responsible->is_primary,
                ) ?? $preparers->first(
                    fn (object $responsible): bool => (bool) $responsible->is_primary,
                ) ?? $preparers->first();

                $this->setOnlyPrimary($inspectionId, 'preparer', $primary?->id);
            });
        }

        $groups = DB::table('inspection_responsibles')
            ->select(['inspection_id', 'responsibility'])
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $responsibles = DB::table('inspection_responsibles')
                ->where('inspection_id', $group->inspection_id)
                ->where('responsibility', $group->responsibility)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get();

            $this->setOnlyPrimary(
                $group->inspection_id,
                $group->responsibility,
                $responsibles->first()?->id,
            );
        }

        $this->updateOfficialDemoNames();
    }

    public function down(): void
    {
        // A origem entre Inspetor e Preparador não pode ser reconstruída sem ambiguidade.
    }

    private function setOnlyPrimary(int|string $inspectionId, string $responsibility, mixed $primaryId): void
    {
        if ($primaryId === null) {
            return;
        }

        DB::table('inspection_responsibles')
            ->where('inspection_id', $inspectionId)
            ->where('responsibility', $responsibility)
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('inspection_responsibles')
            ->where('id', $primaryId)
            ->update(['is_primary' => true, 'updated_at' => now()]);
    }

    private function earliestDate(mixed $first, mixed $second): mixed
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return $first <= $second ? $first : $second;
    }

    private function updateOfficialDemoNames(): void
    {
        $demoOrganizationIds = DB::table('organizations')
            ->where('is_demo', true)
            ->pluck('id');

        if ($demoOrganizationIds->isEmpty()) {
            return;
        }

        foreach ([
            ['email' => 'mariana.costa@vistoria.test', 'from' => 'Mariana Costa — Inspetora Civil', 'to' => 'Mariana Costa — Técnica Civil'],
            ['email' => 'ana.mendes@vistoria.test', 'from' => 'Ana Paula Mendes — Revisora Técnica', 'to' => 'Ana Paula Mendes — Verificadora Técnica'],
        ] as $rename) {
            DB::table('users')
                ->whereIn('organization_id', $demoOrganizationIds)
                ->where('email', $rename['email'])
                ->where('name', $rename['from'])
                ->update(['name' => $rename['to'], 'updated_at' => now()]);
        }
    }
};
