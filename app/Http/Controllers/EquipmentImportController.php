<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Equipments\ImportEquipmentsFromCsv;
use App\Actions\Equipments\UpdateEquipmentsFromCsv;
use App\Http\Requests\Equipments\ConfirmEquipmentImportRequest;
use App\Http\Requests\Equipments\PreviewEquipmentImportRequest;
use App\Models\Client;
use App\Models\Equipment;
use App\Services\Equipments\EquipmentCsv;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EquipmentImportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function create(Request $request): InertiaResponse|RedirectResponse
    {
        $this->authorize('create', Equipment::class);

        if ($request->filled('review')) {
            $token = (string) $request->query('review');
            $state = $this->ownedCacheValue($request, $this->cacheKey($token));
            $plan = $this->ownedCacheValue($request, $this->updatePlanCacheKey($token));

            if ($state === null || $plan === null || ($state['mode'] ?? 'create') !== 'update') {
                return $this->expiredRedirect('A prévia de atualização expirou. Envie o CSV novamente.');
            }

            return $this->page(review: $this->reviewPayload($token, $plan['plan'], $request), mode: 'update');
        }

        if ($request->filled('preview')) {
            $state = $this->ownedCacheValue($request, $this->cacheKey((string) $request->query('preview')));

            if ($state === null) {
                return $this->expiredRedirect('A prévia expirou. Envie o CSV novamente.');
            }

            return $this->page(
                preview: $this->previewPayload((string) $request->query('preview'), $state),
                mode: $state['mode'] ?? 'create',
            );
        }

        if ($request->filled('result')) {
            $state = $this->ownedCacheValue($request, $this->resultCacheKey((string) $request->query('result')));

            if ($state === null) {
                return $this->expiredRedirect('O resultado da importação expirou. Envie o CSV novamente.');
            }

            return $this->page(result: $state['result'], mode: $state['mode'] ?? 'create');
        }

        return $this->page(mode: $request->query('mode') === 'update' ? 'update' : 'create');
    }

    public function legacyPreview(Request $request): RedirectResponse
    {
        $this->authorize('create', Equipment::class);

        return redirect()->route('equipments.import.create');
    }

    public function preview(PreviewEquipmentImportRequest $request, EquipmentCsv $csv): RedirectResponse
    {
        $this->authorize('create', Equipment::class);
        $parsed = $csv->read($request->file('file'));
        $token = (string) Str::ulid();

        Cache::put($this->cacheKey($token), [
            'user_id' => $request->user()->getKey(),
            'organization_id' => $request->user()->organization_id,
            'columns' => $parsed['columns'],
            'rows' => $parsed['rows'],
            'mode' => $request->validated('mode') ?? 'create',
        ], now()->addMinutes(30));

        return redirect()->route('equipments.import.create', ['preview' => $token]);
    }

    public function confirm(
        ConfirmEquipmentImportRequest $request,
        EquipmentCsv $csv,
        ImportEquipmentsFromCsv $import,
    ): RedirectResponse {
        $this->authorize('create', Equipment::class);
        $token = $request->validated('token');
        $state = Cache::get($this->cacheKey($token));

        if (! is_array($state)
            || ($state['user_id'] ?? null) !== $request->user()->getKey()
            || ($state['organization_id'] ?? null) !== $request->user()->organization_id
            || ($state['mode'] ?? 'create') !== 'create') {
            throw ValidationException::withMessages(['token' => 'A prévia expirou. Envie o CSV novamente.']);
        }

        $mapping = $csv->validateMapping($state['columns'], $request->validated('mapping'));
        $result = $import->handle($request->user(), $state, $mapping);
        Cache::forget($this->cacheKey($token));
        Cache::put($this->resultCacheKey($token), [
            'user_id' => $request->user()->getKey(),
            'organization_id' => $request->user()->organization_id,
            'result' => $result,
            'mode' => 'create',
        ], now()->addMinutes(30));

        return redirect()->route('equipments.import.create', ['result' => $token]);
    }

    public function planUpdate(
        ConfirmEquipmentImportRequest $request,
        EquipmentCsv $csv,
        UpdateEquipmentsFromCsv $updater,
    ): RedirectResponse {
        $this->authorize('create', Equipment::class);
        $token = $request->validated('token');
        $state = $this->ownedCacheValue($request, $this->cacheKey($token));

        if ($state === null || ($state['mode'] ?? 'create') !== 'update') {
            throw ValidationException::withMessages(['token' => 'A prévia de atualização expirou. Envie o CSV novamente.']);
        }

        $mapping = $csv->validateMapping($state['columns'], $request->validated('mapping'), 'update');
        $plan = $updater->plan($request->user(), $state, $mapping);
        Cache::put($this->updatePlanCacheKey($token), [
            'user_id' => $request->user()->getKey(),
            'organization_id' => $request->user()->organization_id,
            'plan' => $plan,
        ], now()->addMinutes(30));

        return redirect()->route('equipments.import.create', ['review' => $token]);
    }

    public function confirmUpdate(Request $request, UpdateEquipmentsFromCsv $updater): RedirectResponse
    {
        $this->authorize('create', Equipment::class);
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'confirm_related_records_edit' => ['nullable', 'boolean'],
        ]);
        $token = $validated['token'];
        $lock = Cache::lock("equipment-import-update-confirm:{$token}", 600);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['token' => 'Esta atualização já está sendo processada.']);
        }

        try {
            $state = $this->ownedCacheValue($request, $this->cacheKey($token));
            $planned = $this->ownedCacheValue($request, $this->updatePlanCacheKey($token));
            if ($state === null || $planned === null || ($state['mode'] ?? 'create') !== 'update') {
                throw ValidationException::withMessages(['token' => 'A prévia de atualização expirou. Envie o CSV novamente.']);
            }

            $result = $updater->apply($request->user(), $planned['plan'], (bool) ($validated['confirm_related_records_edit'] ?? false));
            Cache::forget($this->cacheKey($token));
            Cache::forget($this->updatePlanCacheKey($token));
            Cache::put($this->resultCacheKey($token), [
                'user_id' => $request->user()->getKey(),
                'organization_id' => $request->user()->organization_id,
                'mode' => 'update',
                'result' => $result,
            ], now()->addMinutes(30));
        } finally {
            $lock->release();
        }

        return redirect()->route('equipments.import.create', ['result' => $token]);
    }

    /** @param array{token:string, columns:array<int, array{key:string, label:string}>, mapping:array<string, ?string>, samples:array<int, array{line:int, values:array<string, string>}>, total:int}|null $preview
     * @param  array{total:int, created:int, rejected:array<int, array{line:int, maintenance_item_code:?string, tag:?string, reason:string}>}|null  $result
     */
    private function page(?array $preview = null, ?array $result = null, ?array $review = null, string $mode = 'create'): InertiaResponse
    {
        return Inertia::render('Equipments/Import', [
            'preview' => $preview,
            'result' => $result,
            'review' => $review,
            'mode' => $mode,
            'field_options' => collect(EquipmentCsv::FIELDS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label, 'required' => $mode === 'update' ? $value === 'maintenance_item_code' : in_array($value, EquipmentCsv::REQUIRED_FIELDS, true)])
                ->values()
                ->all(),
            'preview_url' => route('equipments.import.preview'),
            'confirm_url' => route('equipments.import.confirm'),
            'plan_update_url' => route('equipments.import.update.plan'),
            'confirm_update_url' => route('equipments.import.update.confirm'),
            'create_mode_url' => route('equipments.import.create'),
            'update_mode_url' => route('equipments.import.create', ['mode' => 'update']),
            'index_url' => route('equipments.index'),
            'client_action' => $mode === 'create' ? $this->clientAction() : null,
        ]);
    }

    /** @return array{url:string, label:string}|null */
    private function clientAction(): ?array
    {
        $client = Client::query()
            ->forOrganization($this->tenant->id())
            ->first();

        if ($client === null) {
            return [
                'url' => route('clients.create'),
                'label' => 'Cadastrar cliente',
            ];
        }

        if (! $client->isActive()) {
            return [
                'url' => route('clients.show', $client),
                'label' => 'Gerenciar cliente',
            ];
        }

        return null;
    }

    private function cacheKey(string $token): string
    {
        return "equipment-import-preview:{$token}";
    }

    private function resultCacheKey(string $token): string
    {
        return "equipment-import-result:{$token}";
    }

    private function updatePlanCacheKey(string $token): string
    {
        return "equipment-import-update-plan:{$token}";
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function reviewPayload(string $token, array $plan, Request $request): array
    {
        $perPage = 50;
        $pages = max(1, (int) ceil(count($plan['rows']) / $perPage));
        $page = min($pages, max(1, (int) $request->query('page', 1)));
        $rows = array_slice($plan['rows'], ($page - 1) * $perPage, $perPage);

        return [
            'token' => $token,
            'summary' => $plan['summary'],
            'rows' => array_map(fn (array $row): array => array_intersect_key($row, array_flip([
                'line', 'maintenance_item_code', 'tag', 'status', 'reason', 'changes', 'related',
            ])), $rows),
            'page' => $page,
            'pages' => $pages,
            'previous_url' => $page > 1 ? route('equipments.import.create', ['review' => $token, 'page' => $page - 1]) : null,
            'next_url' => $page < $pages ? route('equipments.import.create', ['review' => $token, 'page' => $page + 1]) : null,
        ];
    }

    /** @param array{columns:array<int, array{key:string, label:string}>, rows:array<int, array{line:int, values:array<string, string>}>} $state
     * @return array{token:string, columns:array<int, array{key:string, label:string}>, mapping:array<string, ?string>, samples:array<int, array{line:int, values:array<string, string>}>, total:int}
     */
    private function previewPayload(string $token, array $state): array
    {
        $csv = app(EquipmentCsv::class);

        return [
            'token' => $token,
            'columns' => $state['columns'],
            'mapping' => $csv->suggestMapping($state['columns']),
            'samples' => array_slice($state['rows'], 0, 5),
            'total' => count($state['rows']),
        ];
    }

    /** @return array<string, mixed>|null */
    private function ownedCacheValue(Request $request, string $key): ?array
    {
        $state = Cache::get($key);

        if (! is_array($state)
            || ($state['user_id'] ?? null) !== $request->user()->getKey()
            || ($state['organization_id'] ?? null) !== $request->user()->organization_id) {
            return null;
        }

        return $state;
    }

    private function expiredRedirect(string $message): RedirectResponse
    {
        return redirect()->route('equipments.import.create')->with('error', $message);
    }
}
