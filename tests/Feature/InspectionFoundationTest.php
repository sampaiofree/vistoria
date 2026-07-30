<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Inspections\CreateInspection;
use App\Enums\InspectionType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InspectionFoundationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_creations_persist_only_one_open_inspection(): void
    {
        if (! class_exists(Equipment::class) || ! class_exists(InspectionType::class)) {
            $this->markTestSkipped(
                'A concorrência de inspeções depende da implementação do módulo 05 e da fundação completa do módulo 06.',
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped(
                'SQLite does not implement the row-level locking required by this concurrency test; run it with MySQL.',
            );
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('This test requires the pcntl extension to create concurrent workers.');
        }

        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $resultDirectory = storage_path('framework/testing/inspection-concurrency-'.uniqid());
        mkdir($resultDirectory, 0777, true);

        $children = [];

        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runCreationWorker(
                    $worker,
                    $organization->getKey(),
                    $actor->getKey(),
                    $equipment->getKey(),
                    $resultDirectory,
                );
            }

            $this->assertGreaterThan(0, $pid, 'Unable to fork the concurrent inspection worker.');
            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        DB::purge();
        DB::reconnect();

        $this->assertSame(
            ['created', 'rejected'],
            collect(glob($resultDirectory.'/*'))->map(fn (string $file): string => trim(file_get_contents($file)))
                ->sort()->values()->all(),
        );
        $this->assertSame(1, Inspection::query()
            ->where('organization_id', $organization->getKey())
            ->where('equipment_id', $equipment->getKey())
            ->count());
    }

    public function test_creating_initial_inspection_generates_snapshot_number_and_history(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()
            ->for($organization)
            ->create();
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();

        $tenant = app(TenantContext::class);
        $tenant->set($organization);

        $inspection = app(CreateInspection::class)->handle(
            $actor,
            $equipment,
            [
                'inspection_type' => InspectionType::Initial->value,
                'scheduled_at' => '2026-07-30',
                'general_notes' => 'Notas de teste',
            ],
        );

        $this->assertMatchesRegularExpression('/^INS-\d{4}-\d{6}$/', (string) $inspection->number);
        $this->assertSame('planned', $inspection->status->value);
        $this->assertSame('initial', $inspection->inspection_type->value);
        $this->assertSame('2026-07-30', $inspection->scheduled_for?->toDateString());
        $this->assertSame($equipment->tag, $inspection->context_snapshot['equipment']['tag']);
        $this->assertSame(1, $inspection->statusHistories()->count());
        $this->assertSame('Inspeção criada.', $inspection->statusHistories()->first()->reason);

        $originalTag = $inspection->context_snapshot['equipment']['tag'];
        $equipment->update(['tag' => 'EQ-ALTERADO']);

        $this->assertSame(
            $originalTag,
            $inspection->refresh()->context_snapshot['equipment']['tag'],
        );
    }

    private function runCreationWorker(
        int $worker,
        int $organizationId,
        int $actorId,
        int $equipmentId,
        string $resultDirectory,
    ): never {
        DB::purge();
        DB::reconnect();

        // Keep the first acquired equipment lock briefly so that the other process
        // necessarily attempts its creation while that transaction is still open.
        Event::listen(QueryExecuted::class, static function (QueryExecuted $query): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                usleep(300_000);
            }
        });

        $tenant = app(TenantContext::class);
        $tenant->set(Organization::query()->findOrFail($organizationId));

        try {
            app(CreateInspection::class)->handle(
                User::query()->findOrFail($actorId),
                Equipment::query()->findOrFail($equipmentId),
                ['inspection_type' => InspectionType::Initial->value],
            );
            $result = 'created';
        } catch (ValidationException) {
            $result = 'rejected';
        }

        file_put_contents($resultDirectory.'/'.$worker, $result);
        exit(0);
    }
}
