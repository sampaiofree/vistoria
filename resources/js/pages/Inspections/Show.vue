<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import ReportMetadataPanel from '@/components/domain/inspections/ReportMetadataPanel.vue';
import InspectionRevisionPanel from '@/components/domain/inspections/InspectionRevisionPanel.vue';
import GeneralAspectsPanel from '@/components/domain/inspections/GeneralAspectsPanel.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTimeline from '@/components/domain/inspections/InspectionTimeline.vue';
import TransitionForm from '@/components/domain/inspections/TransitionForm.vue';
import CorrectionRequestsPanel from '@/components/domain/inspections/CorrectionRequestsPanel.vue';
import AssessmentProgress from '@/components/domain/view-first/AssessmentProgress.vue';
import CivilClassificationBadge from '@/components/domain/view-first/CivilClassificationBadge.vue';
import DefectCard from '@/components/domain/view-first/DefectCard.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';
import PhotoGallery from '@/components/domain/view-first/PhotoGallery.vue';
import ReportSection from '@/components/domain/view-first/ReportSection.vue';
import ReportPreview from '@/components/domain/view-first/ReportPreview.vue';
import ClassificationSummaryTable from '@/components/domain/inspections/ClassificationSummaryTable.vue';
import InspectionLocationReportMap from '@/components/domain/inspection-locations/InspectionLocationReportMap.vue';
import {
    captureReportPages,
    downloadReportDoc,
    downloadReportPdf,
    reportFilename,
} from '@/lib/reportExport.js';

const props = defineProps({
    inspection: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    tabs: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'overview' },
    content: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    report_metadata: { type: Object, default: () => ({}) },
    general_aspects: { type: Object, default: () => ({}) },
    emission_options: { type: Array, default: () => [] },
    assignment_options: { type: Object, default: () => ({ users: [], roles: [] }) },
    transitions: { type: Array, default: () => [] },
    index_url: { type: String, required: true },
});

const activeSituation = ref('active');
const criticalOnly = ref(false);
const pendingForMeOnly = ref(false);
const activeView = ref('blocks');
const reportLayoutReady = ref(true);
const reportPreview = ref(null);
const exportingFormat = ref(null);
const exportStatus = ref('');
const exportError = ref('');

if (typeof window !== 'undefined') {
    const storedView = window.localStorage.getItem('vistoria.defects.view-mode');
    activeView.value = ['blocks', 'list'].includes(storedView) ? storedView : 'blocks';
}

const sectionTitles = {
    overview: 'Visão geral',
    defects: 'Avarias',
    classifications: 'Classificação e Notas M2',
    photos: 'Fotografias',
    history: 'Histórico',
    report: 'Relatório',
};

const inspectionContextNumber = computed(() => props.inspection.number || 'Inspeção');
const pageTitle = computed(() => sectionTitles[props.active_tab] || 'Inspeção');
const pageSubtitle = computed(() => `${inspectionContextNumber.value} · ${props.inspection.equipment.tag} — ${props.inspection.equipment.name}`);

const defects = computed(() => props.content?.items ?? []);
const defectFilters = computed(() => props.content?.filters ?? {});
const situationFilters = computed(() => defectFilters.value.situations ?? []);
const criticalCount = computed(() => defectFilters.value.critical_count ?? 0);
const pendingForMeCount = computed(() => defectFilters.value.pending_for_current_user_count ?? 0);
const generalCorrectionRequests = computed(() => props.content?.general_correction_requests ?? { history: [] });

const filteredDefects = computed(() => defects.value.filter((defect) => {
    const matchesSituation = (() => {
        switch (activeSituation.value) {
        case 'active':
            return defect.is_repaired !== true;
        case 'pending':
            return defect.is_pending === true || defect.assessment?.status === 'draft';
        case 'treated':
            return defect.is_repaired === true || defect.assessment?.condition === 'treated';
        case 'canceled':
            return defect.assessment?.condition === 'canceled';
        case 'canceled_sr':
            return defect.assessment?.condition === 'canceled_sr';
        default:
            return true;
        }
    })();

    return matchesSituation
        && (!criticalOnly.value || defect.classification?.is_critical === true)
        && (!pendingForMeOnly.value || defect.has_pending_correction_for_current_user === true);
}));

function setView(view) {
    activeView.value = view;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem('vistoria.defects.view-mode', view);
    }
}

const reportSections = computed(() => props.content?.sections ?? []);
const reportEvidence = computed(() => reportSections.value.find((section) => section.key === 'evidence')?.items ?? []);
const reportResponsibles = computed(() => reportSections.value.find((section) => section.key === 'responsibles')?.items ?? []);
const reportLocations = computed(() => props.content?.locations ?? []);
const reportQuantities = computed(() => props.content?.quantities ?? {});
const reportValidation = computed(() => props.content?.validation ?? {});
const reportFindings = computed(() => props.content?.findings ?? []);
const classificationSummary = computed(() => props.content?.classification_summary ?? { categories: [] });
const m2Form = useForm({
    links: (props.content?.classification_summary?.categories ?? []).flatMap((category) => category.rows.filter((row) => row.defect_count > 0).map((row) => ({
        category: category.code,
        classification_code: row.classification_code,
        sap_number: row.sap_m2_number ?? '',
    }))),
});

function saveM2Links() {
    if (!classificationSummary.value.can_edit_m2 || !classificationSummary.value.m2_update_url) return;
    m2Form.put(classificationSummary.value.m2_update_url, { preserveScroll: true });
}

function updateM2Link({ category, classificationCode, sapNumber }) {
    const link = m2Form.links.find((item) => item.category === category && item.classification_code === classificationCode);

    if (link) link.sap_number = sapNumber;
}

const photoStatusLabels = {
    ready: 'Disponíveis',
    processing: 'Processando',
    pending: 'Pendentes',
    failed: 'Falhas',
};

function printReport() {
    if (!reportLayoutReady.value || exportingFormat.value) return;
    window.print();
}

async function exportReport(format) {
    if (!reportLayoutReady.value || exportingFormat.value) return;

    const elements = reportPreview.value?.getPageElements?.() || [];
    exportingFormat.value = format;
    exportError.value = '';
    exportStatus.value = 'Preparando páginas…';

    try {
        const pages = await captureReportPages(elements, ({ current, total }) => {
            exportStatus.value = `Capturando página ${current} de ${total}…`;
        });
        const filename = reportFilename(props.content.number || props.inspection.number);
        exportStatus.value = format === 'pdf' ? 'Montando PDF…' : 'Montando DOC…';

        if (format === 'pdf') {
            await downloadReportPdf(pages, filename);
        } else {
            await downloadReportDoc(pages, filename);
        }
    } catch (error) {
        console.error(error);
        exportError.value = 'Não foi possível gerar o arquivo. Verifique as imagens do relatório e tente novamente.';
    } finally {
        exportingFormat.value = null;
        exportStatus.value = '';
    }
}
</script>

<template>
    <AppLayout
        :title="pageTitle"
        :subtitle="pageSubtitle"
        wide
    >
        <template #actions>
            <InspectionStatusBadge :status="inspection.status" />
            <div v-if="active_tab !== 'overview'" class="hidden min-w-44 sm:block">
                <AssessmentProgress
                    :progress="{ completed: summary.completed, total: summary.total, percentage: summary.progress_percent }"
                    label="Avaliações"
                />
            </div>
            <Link
                v-if="inspection.equipment?.show_url"
                :href="inspection.equipment.show_url"
                class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400"
            >
                Equipamento
            </Link>
            <details class="relative" v-if="capabilities.update_planned">
                <summary class="cursor-pointer list-none rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400">
                    Mais ações
                </summary>
                <div class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 text-left shadow-xl">
                    <Link :href="capabilities.update_planned.action" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        Editar planejamento
                    </Link>
                    <Link :href="index_url" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                        Voltar às inspeções
                    </Link>
                </div>
            </details>
        </template>

        <div class="print-hidden mt-5 lg:hidden">
            <InspectionTabs :tabs="tabs" :active="active_tab" />
        </div>

        <div v-if="active_tab === 'overview'" class="print-hidden mt-6 space-y-6">
            <ReportMetadataPanel
                :inspection="inspection"
                :metadata="report_metadata"
                :emission-options="emission_options"
                :capability="capabilities.manage_report_metadata"
            />
            <InspectionRevisionPanel :inspection="inspection" :capability="capabilities.update_report_revision" />

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Planejamento</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm text-slate-500">Data inicial planejada</p>
                        <p class="mt-1 font-semibold text-slate-950">{{ inspection.planned_start_on || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500">Prazo final planejado</p>
                        <p class="mt-1 font-semibold text-slate-950">{{ inspection.planned_end_on || '—' }}</p>
                    </div>
                </div>
            </section>

            <GeneralAspectsPanel :aspects="general_aspects" />

            <CorrectionRequestsPanel
                v-if="generalCorrectionRequests.items?.length || generalCorrectionRequests.history?.length"
                :correction="generalCorrectionRequests"
                title="Solicitações gerais de correção"
                empty_label="Nenhuma solicitação geral registrada."
            />

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Workflow</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Ações da inspeção</h2>
                    <p class="mt-1 text-sm text-slate-500">As ações disponíveis dependem da etapa atual e da responsabilidade do usuário.</p>
                </div>
                <div v-if="transitions.length" class="mt-5 grid gap-4 md:grid-cols-2">
                    <TransitionForm v-for="transition in transitions" :key="transition.key" :transition="transition" />
                </div>
                <p v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">Nenhuma ação disponível para este usuário nesta etapa.</p>
            </section>
        </div>

        <div v-else-if="active_tab === 'defects'" class="print-hidden mt-6 space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-end gap-3" aria-label="Filtrar avarias">
                        <label class="flex min-w-52 flex-col gap-1.5 text-sm font-semibold text-slate-700">
                            <span>Situação da avaria</span>
                            <select v-model="activeSituation" class="min-h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-800 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500">
                                <option v-for="filter in situationFilters" :key="filter.key" :value="filter.key">
                                    {{ filter.label }} ({{ filter.count }})
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            :class="criticalOnly ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            :aria-pressed="criticalOnly"
                            @click="criticalOnly = !criticalOnly"
                        >
                            Críticas
                            <span class="rounded-full px-1.5 text-xs" :class="criticalOnly ? 'bg-white/15' : 'bg-white'">{{ criticalCount }}</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            :class="pendingForMeOnly ? 'border-amber-700 bg-amber-700 text-white' : 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100'"
                            :aria-pressed="pendingForMeOnly"
                            @click="pendingForMeOnly = !pendingForMeOnly"
                        >
                            Pendências para mim
                            <span class="rounded-full px-1.5 text-xs" :class="pendingForMeOnly ? 'bg-white/15' : 'bg-white'">{{ pendingForMeCount }}</span>
                        </button>
                    </div>
                    <div class="inline-flex shrink-0 rounded-xl border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Modo de visualização">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="activeView === 'blocks' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            :aria-pressed="activeView === 'blocks'"
                            @click="setView('blocks')"
                        >
                            Blocos
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-xs font-semibold transition"
                            :class="activeView === 'list' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            :aria-pressed="activeView === 'list'"
                            @click="setView('list')"
                        >
                            Lista
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="activeView === 'blocks'" class="grid gap-4 xl:grid-cols-2">
                <DefectCard v-for="defect in filteredDefects" :id="`defect-${defect.public_id}`" :key="defect.id" :defect="defect" class="scroll-mt-24" />
            </div>
            <div v-else class="space-y-2">
                <DefectCard v-for="defect in filteredDefects" :id="`defect-${defect.public_id}`" :key="defect.id" :defect="defect" variant="list" class="scroll-mt-24" />
            </div>
            <div v-if="filteredDefects.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-500">
                {{ pendingForMeOnly ? 'Nenhuma pendência para você corresponde aos filtros selecionados.' : 'Nenhuma avaria corresponde aos filtros selecionados.' }}
            </div>
        </div>

        <div v-else-if="active_tab === 'classifications'" class="print-hidden mt-6 space-y-6">
            <section class="mx-auto max-w-7xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Resumo técnico</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Classificação e Notas M2</h2>
                        <p class="mt-1 text-sm text-slate-500">Preencha a Nota M2 para cada classificação com avarias publicadas.</p>
                    </div>
                    <button v-if="classificationSummary.can_edit_m2" type="button" class="rounded-xl bg-teal-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="m2Form.processing" @click="saveM2Links">Salvar Notas M2</button>
                </div>
                <div class="mt-6 overflow-x-auto">
                    <ClassificationSummaryTable
                        v-if="classificationSummary.categories?.length"
                        :summary="classificationSummary"
                        :editable="classificationSummary.can_edit_m2"
                        :m2-links="m2Form.links"
                        @update:m2="updateM2Link"
                    />
                </div>
            </section>
        </div>

        <div v-else-if="active_tab === 'photos'" class="print-hidden mt-6 space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Evidências técnicas</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Galeria da inspeção</h2>
                        <p class="mt-1 text-sm text-slate-500">Arquivos privados vinculados oficialmente às avaliações da inspeção.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span v-for="(count, status) in (content.counts || {})" :key="status" class="rounded-full bg-slate-100 px-3 py-1.5">
                            {{ photoStatusLabels[status] || status }} · {{ count }}
                        </span>
                    </div>
                </div>
                <div class="mt-6">
                    <PhotoGallery :photos="content.items || []" />
                </div>
            </section>
        </div>

        <div v-else-if="active_tab === 'history'" class="print-hidden mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.7fr)]">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Rastreabilidade</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Histórico de status</h2>
                <div class="mt-6">
                    <InspectionTimeline :history="content.items || []" />
                </div>
            </section>
            <aside class="space-y-4">
                <Link v-if="content.previous_inspection" :href="content.previous_inspection.show_url" class="block rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Inspeção anterior</p>
                    <h3 class="mt-2 font-semibold text-teal-700">{{ content.previous_inspection.number }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ content.previous_inspection.status_label }}</p>
                </Link>
                <article v-for="nextInspection in (content.next_inspections || [])" :key="nextInspection.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Reinspeção vinculada</p>
                    <Link :href="nextInspection.show_url" class="mt-2 block font-semibold text-teal-700">{{ nextInspection.number }}</Link>
                </article>
            </aside>
        </div>

        <div v-else-if="active_tab === 'report'" class="mt-6">
            <div class="print-hidden mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-3xl flex-1 text-sm leading-6 text-slate-500">
                    Esta prévia usa os dados persistidos da inspeção.
                </p>
                <div class="max-w-sm">
                    <div class="flex gap-2">
                        <button
                            v-if="content.print_enabled"
                            type="button"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            :disabled="!reportLayoutReady || exportingFormat"
                            :class="{ 'cursor-wait opacity-50': !reportLayoutReady || exportingFormat }"
                            @click="printReport"
                        >
                            {{ reportLayoutReady ? 'Imprimir prévia' : 'Preparando páginas…' }}
                        </button>
                        <button
                            v-if="content.print_enabled"
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-teal-500 hover:text-teal-700 disabled:cursor-wait disabled:opacity-50"
                            :disabled="!reportLayoutReady || exportingFormat"
                            :aria-busy="exportingFormat === 'pdf'"
                            @click="exportReport('pdf')"
                        >
                            {{ exportingFormat === 'pdf' ? 'Gerando PDF…' : 'Gerar PDF' }}
                        </button>
                        <button
                            v-if="content.print_enabled"
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 disabled:cursor-wait disabled:opacity-50"
                            :disabled="!reportLayoutReady || exportingFormat"
                            :aria-busy="exportingFormat === 'doc'"
                            @click="exportReport('doc')"
                        >
                            {{ exportingFormat === 'doc' ? 'Gerando DOC…' : 'Gerar DOC' }}
                        </button>
                        <template v-if="!content.print_enabled">
                            <button
                                v-for="label in ['Imprimir prévia', 'Gerar PDF', 'Gerar DOC']"
                                :key="label"
                                type="button"
                                disabled
                                class="cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400"
                                :title="content.export_disabled_reason"
                            >
                                {{ label }}
                            </button>
                        </template>
                    </div>
                    <p v-if="!content.print_enabled" class="mt-2 text-right text-xs font-medium text-rose-700" role="alert">
                        {{ content.export_disabled_reason }}
                    </p>
                    <p v-if="exportStatus" class="mt-2 text-right text-xs text-slate-500" role="status" aria-live="polite">{{ exportStatus }}</p>
                    <p v-if="exportError" class="mt-2 text-right text-xs font-medium text-rose-700" role="alert">{{ exportError }}</p>
                </div>
            </div>

            <ReportPreview ref="reportPreview" :content="content" @layout-ready="reportLayoutReady = $event" />
        </div>

        <div v-else-if="false" class="mt-6">
            <div class="print-hidden mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="max-w-3xl flex-1 text-sm leading-6 text-slate-500">Prévia baseada nos dados persistidos da inspeção.</p>
                <div class="max-w-sm">
                    <div class="flex gap-2">
                        <button v-if="content.print_enabled" type="button" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500" @click="printReport">
                            Imprimir prévia
                        </button>
                        <button type="button" disabled aria-describedby="pdf-disabled-reason" class="cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400" :title="content.pdf_disabled_reason">
                            Gerar PDF
                        </button>
                    </div>
                    <p id="pdf-disabled-reason" class="mt-2 text-xs leading-5 text-slate-500">{{ content.pdf_disabled_reason }}</p>
                </div>
            </div>

            <article class="report-document mx-auto max-w-5xl overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-950/10">
                <header class="relative overflow-hidden bg-slate-950 px-6 py-10 text-white sm:px-10 sm:py-14">
                    <div class="absolute -right-16 -top-24 h-72 w-72 rounded-full border-[50px] border-teal-400/10"></div>
                    <div class="relative">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-teal-300">{{ content.cover?.eyebrow }}</p>
                        <h2 class="mt-5 max-w-3xl text-3xl font-semibold tracking-tight sm:text-5xl">{{ content.cover?.title }}</h2>
                        <p class="mt-4 text-lg text-slate-300">{{ content.cover?.equipment_tag }} · {{ content.cover?.equipment_name }}</p>
                        <dl class="mt-10 grid gap-5 border-t border-white/10 pt-6 sm:grid-cols-3">
                            <div><dt class="text-xs uppercase tracking-wider text-slate-500">Cliente</dt><dd class="mt-1 font-medium">{{ content.cover?.client }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-slate-500">Inspeção</dt><dd class="mt-1 font-medium">{{ content.cover?.inspection_type }} · {{ content.cover?.inspected_on }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-slate-500">Revisão</dt><dd class="mt-1 font-medium">{{ content.cover?.revision }}</dd></div>
                        </dl>
                    </div>
                </header>

                <div class="space-y-10 px-6 py-8 sm:px-10 sm:py-12">
                    <div v-if="reportValidation.blocked" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-amber-900 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Emissão bloqueada</p>
                        <p class="mt-2 text-sm leading-6">A prévia permanece visível, mas a exportação oficial está bloqueada até a validação técnica destes pontos:</p>
                        <ul class="mt-3 space-y-1.5 text-sm leading-6">
                            <li v-for="issue in reportValidation.issues" :key="issue">• {{ issue }}</li>
                        </ul>
                    </div>

                    <section class="grid gap-5 rounded-3xl bg-slate-50 p-5 sm:grid-cols-[auto_1fr] sm:p-7">
                        <CivilClassificationBadge :code="content.executive_summary?.criticality?.code" :label="content.executive_summary?.criticality?.label" large />
                        <div>
                            <h3 class="text-xl font-semibold text-slate-950">Resumo executivo</h3>
                            <p class="mt-2 font-medium leading-6 text-slate-800">{{ content.executive_summary?.headline }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ content.executive_summary?.description }}</p>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl bg-white p-3 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ content.executive_summary?.metrics?.total ?? '—' }}</p>
                                </div>
                                <div class="rounded-2xl bg-white p-3 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Publicadas</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ content.executive_summary?.metrics?.completed ?? '—' }}</p>
                                </div>
                                <div class="rounded-2xl bg-white p-3 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Fotos</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ content.executive_summary?.metrics?.photo_total ?? '—' }}</p>
                                </div>
                                <div class="rounded-2xl bg-white p-3 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Quantidade</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">{{ content.executive_summary?.metrics?.quantity_total_label ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <ReportSection index="02" title="Mapa de localização" content-class="mt-5 space-y-4">
                        <div class="space-y-5">
                            <section v-for="sheet in reportLocations" :key="sheet.id" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ sheet.category.code }}</p>
                                        <h4 class="mt-1 font-semibold text-slate-950">Localização fotográfica — {{ sheet.category.name }}</h4>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">Folha {{ sheet.number }}</span>
                                </div>
                                <div class="grid gap-4 xl:grid-cols-2">
                                    <article v-for="map in sheet.maps" :key="map.public_id" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                        <div class="border-b border-slate-900 px-3 py-2.5 text-center text-sm font-extrabold uppercase">{{ map.report_title || map.title }}</div>
                                        <InspectionLocationReportMap :map="map" />
                                    </article>
                                </div>
                            </section>
                            <p v-if="!reportLocations.length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">Nenhum mapa de localização cadastrado. O relatório não inferirá localizações a partir de textos.</p>
                        </div>
                    </ReportSection>

                    <ReportSection index="03" title="Avarias e avaliações CIVIL" content-class="mt-5 space-y-4">
                        <article v-for="defect in reportFindings" :key="defect.id" class="break-inside-avoid rounded-2xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ defect.code }}</p>
                                    <h4 class="mt-1 font-semibold text-slate-950">{{ defect.title }}</h4>
                                </div>
                                <CivilClassificationBadge :code="defect.classification?.code" :historical="defect.classification?.historical" />
                            </div>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ defect.location }}</p>
                            <p class="mt-2 text-sm text-slate-500">{{ defect.project }} · {{ defect.item }} · {{ defect.element }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-800">Recomendação: {{ defect.recommendation || 'Acompanhar conforme programação técnica.' }}</p>
                        </article>
                    </ReportSection>

                    <ReportSection index="04" title="Fichas fotográficas" content-class="mt-5 grid gap-4 sm:grid-cols-2">
                        <article v-for="(photo, index) in reportEvidence" :key="photo.id" class="break-inside-avoid overflow-hidden rounded-2xl border border-slate-200">
                            <div class="relative aspect-[4/3] bg-gradient-to-br from-slate-300 via-slate-400 to-slate-600">
                                <span class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.12)_1px,transparent_1px)] bg-[size:32px_32px]"></span>
                                <span class="absolute bottom-3 right-3 rounded-lg bg-slate-950/70 px-2 py-1 text-xs font-bold text-white">{{ String(index + 1).padStart(2, '0') }}</span>
                                <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2 py-1 text-[10px] font-bold uppercase text-slate-700">{{ photo.role_label || 'Fotografia' }}</span>
                                <span class="absolute left-3 bottom-3 rounded-full bg-slate-950/70 px-2 py-1 text-[10px] font-bold uppercase text-white">{{ photo.photo_interval || '—' }}</span>
                            </div>
                            <div class="p-3">
                                <p class="font-semibold text-slate-900">{{ photo.title }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ photo.caption }}</p>
                            </div>
                        </article>
                    </ReportSection>

                    <ReportSection index="05" title="Quantitativo consolidado" content-class="mt-5 space-y-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ reportQuantities.total_label || '—' }}</p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Exportável</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ reportQuantities.exportable_total_label || '—' }}</p>
                            </article>
                            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Unidade</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ reportQuantities.unit || '—' }}</p>
                            </article>
                        </div>
                        <div class="overflow-x-auto rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Classe</th>
                                        <th class="px-4 py-3">Total</th>
                                        <th class="px-4 py-3">Qtd.</th>
                                        <th class="px-4 py-3">Unidade</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="item in (reportQuantities.by_class || [])" :key="item.code">
                                        <td class="px-4 py-3">
                                            <CivilClassificationBadge :code="item.code" :label="item.label" />
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ item.total_label }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ item.count }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ item.unit }}</td>
                                    </tr>
                                    <tr v-if="!(reportQuantities.by_class || []).length">
                                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sem consolidado disponível.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </ReportSection>

                    <ReportSection index="06" title="Responsabilidade técnica" content-class="mt-4">
                        <ul class="space-y-3">
                            <li v-for="item in reportResponsibles" :key="`${item.user.id}-${item.responsibility}`">
                                <p class="font-medium text-slate-900">{{ item.user.name }}</p>
                                <p class="text-sm text-slate-500">{{ item.responsibility_label }}</p>
                            </li>
                        </ul>
                    </ReportSection>
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-6 py-5 text-xs text-slate-500 sm:px-10">
                    <span>{{ content.cover?.provider || 'Vistoria Serviços de Inspeção Ltda.' }}</span>
                    <span>{{ content.revision || '—' }}</span>
                </footer>
            </article>
        </div>
    </AppLayout>
</template>

<style>
@page {
    size: A4 portrait;
    margin: 0;
}

.report-preview-pages.report-exporting .report-a4-page {
    width: 210mm !important;
    height: 297mm !important;
    min-height: 297mm !important;
    margin: 0 !important;
    box-shadow: none !important;
}

@media print {
    body {
        background: white !important;
    }

    body * {
        visibility: hidden !important;
    }

    .report-document,
    .report-document * {
        visibility: visible !important;
    }

    .report-preview-pages,
    .report-preview-pages * {
        visibility: visible !important;
    }

    .report-document {
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        max-width: none !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .report-preview-pages {
        position: absolute;
        inset: 0 auto auto 0;
        width: 210mm;
        max-width: none !important;
    }

    .break-inside-avoid {
        break-inside: avoid;
    }
}
</style>
