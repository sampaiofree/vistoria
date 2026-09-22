<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\GeneralAspectsTemplateRequest;
use App\Models\GeneralAspectsTemplate;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class GeneralAspectsTemplateController extends Controller
{
    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);

        $templates = GeneralAspectsTemplate::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get()
            ->map(fn (GeneralAspectsTemplate $template): array => $this->listPayload($template))
            ->all();

        return Inertia::render('Settings/InspectionReports/GeneralAspectsTemplates/Index', [
            'templates' => $templates,
            'create_url' => route('settings.inspection-report.general-aspects.create'),
        ]);
    }

    public function create(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);

        return Inertia::render('Settings/InspectionReports/GeneralAspectsTemplates/Form', [
            'title' => 'Novo modelo',
            'subtitle' => 'Crie um texto reutilizável para os aspectos gerais do equipamento.',
            'template' => null,
            'action' => route('settings.inspection-report.general-aspects.store'),
            'method' => 'post',
            'cancel_url' => route('settings.inspection-report.general-aspects.index'),
        ]);
    }

    public function store(GeneralAspectsTemplateRequest $request, TenantContext $tenant): RedirectResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);
        $data = $this->normalizedData($request->validated());

        GeneralAspectsTemplate::query()->create([
            ...$data,
            'organization_id' => $tenant->id(),
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()->route('settings.inspection-report.general-aspects.index')->with('success', 'Modelo criado.');
    }

    public function edit(Request $request, TenantContext $tenant, GeneralAspectsTemplate $generalAspectsTemplate): InertiaResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);
        $template = $this->tenantTemplate($tenant, $generalAspectsTemplate);

        return Inertia::render('Settings/InspectionReports/GeneralAspectsTemplates/Form', [
            'title' => 'Editar modelo',
            'subtitle' => 'Atualize o texto reutilizável para os aspectos gerais do equipamento.',
            'template' => $this->payload($template),
            'action' => route('settings.inspection-report.general-aspects.update', $template),
            'method' => 'put',
            'cancel_url' => route('settings.inspection-report.general-aspects.index'),
        ]);
    }

    public function update(GeneralAspectsTemplateRequest $request, TenantContext $tenant, GeneralAspectsTemplate $generalAspectsTemplate): RedirectResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);
        $template = $this->tenantTemplate($tenant, $generalAspectsTemplate);

        $template->update([
            ...$this->normalizedData($request->validated()),
            'updated_by' => $request->user()->getKey(),
        ]);

        return redirect()->route('settings.inspection-report.general-aspects.index')->with('success', 'Modelo atualizado.');
    }

    public function destroy(Request $request, TenantContext $tenant, GeneralAspectsTemplate $generalAspectsTemplate): RedirectResponse
    {
        $this->authorizeOrganizationUpdate($request, $tenant);
        $this->tenantTemplate($tenant, $generalAspectsTemplate)->delete();

        return redirect()->route('settings.inspection-report.general-aspects.index')->with('success', 'Modelo excluído.');
    }

    private function authorizeOrganizationUpdate(Request $request, TenantContext $tenant): void
    {
        $this->authorize('update', $tenant->organization());
    }

    private function tenantTemplate(TenantContext $tenant, GeneralAspectsTemplate $template): GeneralAspectsTemplate
    {
        abort_unless($template->belongsToOrganization($tenant->id()), 404);

        return $template;
    }

    /** @param array<string, mixed> $data @return array{name:string, schema_version:int, document:array<string, mixed>} */
    private function normalizedData(array $data): array
    {
        $documents = app(GeneralAspectsDocument::class);
        $normalized = $documents->normalize(
            (int) $data['schema_version'],
            $documents->withFlatHeadings($data['document']),
            allowPendingTextColor: true,
        );

        if ($normalized === null) {
            throw new \LogicException('O conteúdo do modelo não pode estar vazio.');
        }

        return [
            'name' => TextNormalizer::text((string) $data['name']),
            'schema_version' => $normalized['schema_version'],
            'document' => $normalized['document'],
        ];
    }

    /** @return array<string, mixed> */
    private function payload(GeneralAspectsTemplate $template): array
    {
        return [
            'public_id' => $template->public_id,
            'name' => $template->name,
            'schema_version' => $template->schema_version,
            'document' => $template->document,
            'updated_at' => $template->updated_at?->format('d/m/Y H:i'),
            'edit_url' => route('settings.inspection-report.general-aspects.edit', $template),
            'delete_url' => route('settings.inspection-report.general-aspects.destroy', $template),
        ];
    }

    /** @return array<string, mixed> */
    private function listPayload(GeneralAspectsTemplate $template): array
    {
        return [
            'public_id' => $template->public_id,
            'name' => $template->name,
            'updated_at' => $template->updated_at?->format('d/m/Y H:i'),
            'edit_url' => route('settings.inspection-report.general-aspects.edit', $template),
            'delete_url' => route('settings.inspection-report.general-aspects.destroy', $template),
        ];
    }
}
