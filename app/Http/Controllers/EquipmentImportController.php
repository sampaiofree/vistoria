<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Equipments\ImportEquipmentsFromCsv;
use App\Http\Requests\Equipments\ConfirmEquipmentImportRequest;
use App\Http\Requests\Equipments\PreviewEquipmentImportRequest;
use App\Models\Equipment;
use App\Services\Equipments\EquipmentCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EquipmentImportController extends Controller
{
    public function create(Request $request): InertiaResponse|RedirectResponse
    {
        $this->authorize('create', Equipment::class);

        if ($request->filled('preview')) {
            $state = $this->ownedCacheValue($request, $this->cacheKey((string) $request->query('preview')));

            if ($state === null) {
                return $this->expiredRedirect('A prévia expirou. Envie o CSV novamente.');
            }

            return $this->page($this->previewPayload((string) $request->query('preview'), $state));
        }

        if ($request->filled('result')) {
            $state = $this->ownedCacheValue($request, $this->resultCacheKey((string) $request->query('result')));

            if ($state === null) {
                return $this->expiredRedirect('O resultado da importação expirou. Envie o CSV novamente.');
            }

            return $this->page(null, $state['result']);
        }

        return $this->page();
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
            || ($state['organization_id'] ?? null) !== $request->user()->organization_id) {
            throw ValidationException::withMessages(['token' => 'A prévia expirou. Envie o CSV novamente.']);
        }

        $mapping = $csv->validateMapping($state['columns'], $request->validated('mapping'));
        $result = $import->handle($request->user(), $state, $mapping);
        Cache::forget($this->cacheKey($token));
        Cache::put($this->resultCacheKey($token), [
            'user_id' => $request->user()->getKey(),
            'organization_id' => $request->user()->organization_id,
            'result' => $result,
        ], now()->addMinutes(30));

        return redirect()->route('equipments.import.create', ['result' => $token]);
    }

    /** @param array{token:string, columns:array<int, array{key:string, label:string}>, mapping:array<string, ?string>, samples:array<int, array{line:int, values:array<string, string>}>, total:int}|null $preview
     * @param  array{total:int, created:int, rejected:array<int, array{line:int, maintenance_item_code:?string, tag:?string, reason:string}>}|null  $result
     */
    private function page(?array $preview = null, ?array $result = null): InertiaResponse
    {
        return Inertia::render('Equipments/Import', [
            'preview' => $preview,
            'result' => $result,
            'field_options' => collect(EquipmentCsv::FIELDS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label, 'required' => in_array($value, EquipmentCsv::REQUIRED_FIELDS, true)])
                ->values()
                ->all(),
            'preview_url' => route('equipments.import.preview'),
            'confirm_url' => route('equipments.import.confirm'),
            'index_url' => route('equipments.index'),
        ]);
    }

    private function cacheKey(string $token): string
    {
        return "equipment-import-preview:{$token}";
    }

    private function resultCacheKey(string $token): string
    {
        return "equipment-import-result:{$token}";
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
