<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AssignmentForm from '@/components/domain/inspections/AssignmentForm.vue';
import DefectCreateForm from '@/components/domain/defects/DefectCreateForm.vue';
import InspectionSnapshot from '@/components/domain/inspections/InspectionSnapshot.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTimeline from '@/components/domain/inspections/InspectionTimeline.vue';
import ReferenceDocumentsForm from '@/components/domain/inspections/ReferenceDocumentsForm.vue';
import TransitionForm from '@/components/domain/inspections/TransitionForm.vue';
import AssessmentProgress from '@/components/domain/view-first/AssessmentProgress.vue';
import CivilClassificationBadge from '@/components/domain/view-first/CivilClassificationBadge.vue';
import DefectCard from '@/components/domain/view-first/DefectCard.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';
import PhotoGallery from '@/components/domain/view-first/PhotoGallery.vue';
import ProvisionalDataNotice from '@/components/domain/view-first/ProvisionalDataNotice.vue';
import ReportSection from '@/components/domain/view-first/ReportSection.vue';

const props = defineProps({
    inspection: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    tabs: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'overview' },
    content: { type: Object, default: () => ({}) },
    demo: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
    assignment_options: { type: Object, default: () => ({ users: [], roles: [] }) },
    available_documents: { type: Array, default: () => [] },
    transitions: { type: Array, default: () => [] },
    index_url: { type: String, required: true },
});

const activeFilter = ref('all');

const defects = computed(() => props.content?.items ?? []);
const filters = computed(() => props.content?.filters ?? []);

const filteredDefects = computed(() => defects.value.filter((defect) => {
    switch (activeFilter.value) {
        case 'critical':
            return ['CV-1', 'CV-2'].includes(defect.classification?.code);
        case 'pending':
            return defect.is_pending === true || defect.assessment?.status === 'draft';
        case 'repaired':
            return defect.is_repaired === true || defect.assessment?.condition === 'repaired';
        case 'not_inspected':
            return defect.is_not_inspected === true || defect.assessment?.condition === 'not_inspected';
        default:
            return true;
    }
}));

const reportSections = computed(() => props.content?.sections ?? []);
const reportEvidence = computed(() => reportSections.value.find((section) => section.key === 'evidence')?.items ?? []);
const reportResponsibles = computed(() => reportSections.value.find((section) => section.key === 'responsibles')?.items ?? []);
const reportDocuments = computed(() => reportSections.value.find((section) => section.key === 'documents')?.items ?? []);
const reportLocations = computed(() => props.content?.locations ?? []);
const reportQuantities = computed(() => props.content?.quantities ?? {});
const reportValidation = computed(() => props.content?.validation ?? {});
const reportGeneralAspects = computed(() => props.content?.general_aspects ?? []);
const reportFindings = computed(() => props.content?.findings ?? []);

const photoStatusLabels = {
    ready: 'Disponíveis',
    processing: 'Processando',
    pending: 'Pendentes',
    failed: 'Falhas',
};

function setPrimaryResponsible(url) {
    router.patch(url, {}, { preserveScroll: true });
}

function removeResponsible(url) {
    if (!window.confirm('Remover este responsável?')) {
        return;
    }

    router.delete(url, { preserveScroll: true });
}

function printReport() {
    window.print();
}
</script>

<template>
    <AppLayout
        :title="inspection.number"
        :subtitle="`${inspection.equipment.tag} — ${inspection.equipment.name}`"
        wide
    >
        <div class="print-hidden mb-5 flex flex-wrap items-center justify-between gap-3">
            <Link :href="index_url" class="text-sm font-semibold text-teal-700 hover:text-teal-800">
                ← Voltar às inspeções
            </Link>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="inspection.equipment?.show_url"
                    :href="inspection.equipment.show_url"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400"
                >
                    Ver equipamento
                </Link>
                <details class="relative" v-if="capabilities.update_planned">
                    <summary class="cursor-pointer list-none rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400">
                        Mais ações
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                        <Link :href="capabilities.update_planned.action" class="block rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                            Editar planejamento
                        </Link>
                    </div>
                </details>
            </div>
        </div>

        <section class="print-hidden overflow-hidden rounded-3xl bg-slate-950 text-white shadow-xl shadow-slate-950/10">
            <div class="relative p-6 sm:p-8">
                <div class="pointer-events-none absolute -right-20 -top-28 h-72 w-72 rounded-full border-[52px] border-teal-400/10"></div>
                <div class="relative flex flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <InspectionStatusBadge :status="inspection.status" />
                            <span class="rounded-full border border-white/15 bg-white/8 px-3 py-1 text-xs font-semibold text-slate-200">
                                {{ inspection.inspection_type_label }}
                            </span>
                            <span v-if="inspection.service_order" class="text-xs font-medium text-slate-400">O.S. {{ inspection.service_order }}</span>
                        </div>
                        <h2 class="mt-5 text-2xl font-semibold tracking-tight sm:text-3xl">
                            {{ inspection.equipment.tag }}
                            <span class="font-normal text-slate-400">· {{ inspection.equipment.name }}</span>
                        </h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                            {{ inspection.equipment.client?.name }}<span v-if="inspection.equipment.unit?.name"> · {{ inspection.equipment.unit.name }}</span>
                        </p>
                    </div>

                    <div class="grid w-full gap-3 sm:grid-cols-[minmax(0,1fr)_auto] xl:max-w-xl">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <AssessmentProgress
                                :progress="{ completed: summary.completed, total: summary.total, percentage: summary.progress_percent }"
                                dark
                            />
                        </div>
                        <div class="flex min-w-36 items-center justify-center rounded-2xl border border-white/10 bg-white/5 p-4">
                            <CivilClassificationBadge
                                :code="summary.criticality?.code"
                                :label="summary.criticality?.label"
                                large
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="print-hidden mt-5">
            <InspectionTabs :tabs="tabs" :active="active_tab" />
        </div>

        <div v-if="active_tab === 'overview'" class="print-hidden mt-6 space-y-6">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="metric in (content.metrics || [])" :key="metric.key" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-400">{{ metric.label }}</p>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <strong class="text-2xl font-semibold tracking-tight text-slate-950">{{ metric.value }}</strong>
                        <span class="text-xs font-medium text-slate-500">{{ metric.detail }}</span>
                    </div>
                </article>
            </section>

            <div class="grid gap-6 2xl:grid-cols-[minmax(0,1.55fr)_minmax(22rem,0.75fr)]">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Prioridades da inspeção</p>
                            <h2 class="mt-2 text-xl font-semibold text-slate-950">Pontos que merecem atenção</h2>
                            <p class="mt-1 text-sm text-slate-500">Avarias críticas ou com avaliação pendente.</p>
                        </div>
                        <Link
                            v-if="content.primary_action?.url"
                            :href="content.primary_action.url"
                            class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700"
                        >
                            {{ content.primary_action.label }} →
                        </Link>
                    </div>
                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                        <DefectCard v-for="defect in (content.highlights || [])" :key="defect.id" :defect="defect" />
                        <div v-if="!(content.highlights || []).length" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                            Nenhuma prioridade técnica em aberto.
                        </div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-950">Contexto operacional</h2>
                        <dl class="mt-5 space-y-4">
                            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">Planejada para</dt>
                                <dd class="text-right text-sm font-semibold text-slate-900">{{ inspection.scheduled_at || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">Executada em</dt>
                                <dd class="text-right text-sm font-semibold text-slate-900">{{ inspection.inspected_on || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">Procedimento</dt>
                                <dd class="max-w-52 text-right text-sm font-semibold text-slate-900">{{ inspection.procedure_number || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-sm text-slate-500">Inspeção anterior</dt>
                                <dd class="text-right text-sm font-semibold text-slate-900">
                                    <Link v-if="inspection.previous_inspection" :href="inspection.previous_inspection.show_url" class="text-teal-700">
                                        {{ inspection.previous_inspection.number }}
                                    </Link>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <ProvisionalDataNotice v-if="demo.enabled" :message="demo.provisional_notice" compact />
                </aside>
            </div>

            <section class="grid gap-4 lg:grid-cols-3" aria-label="Resumo técnico da inspeção">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Condições observadas</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-950">Evolução das avarias</h2>
                    <ul class="mt-5 space-y-2.5">
                        <li v-for="item in (summary.condition_breakdown || [])" :key="item.key" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3.5 py-2.5">
                            <span class="text-sm font-medium text-slate-700">{{ item.label }}</span>
                            <strong class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-white px-2 text-xs text-slate-950 shadow-sm">{{ item.count }}</strong>
                        </li>
                    </ul>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Classificação CIVIL</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-950">Distribuição de criticidade</h2>
                    <ul class="mt-5 space-y-2.5">
                        <li v-for="item in (summary.classification_breakdown || [])" :key="item.code" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3.5 py-2.5">
                            <div class="min-w-0">
                                <CivilClassificationBadge :code="item.code" :label="item.label" />
                                <p v-if="item.historical_count" class="mt-1 text-[11px] text-slate-500">{{ item.historical_count }} classificação histórica</p>
                            </div>
                            <strong class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-white px-2 text-xs text-slate-950 shadow-sm">{{ item.count }}</strong>
                        </li>
                    </ul>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Responsabilidade técnica</p>
                    <h2 class="mt-2 text-lg font-semibold text-slate-950">Equipe e referência</h2>
                    <ul class="mt-5 space-y-3">
                        <li v-for="item in (inspection.responsibles || []).slice(0, 3)" :key="`${item.user.id}-${item.responsibility}`" class="border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                            <p class="text-sm font-semibold text-slate-900">{{ item.user.name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ item.responsibility_label }}</p>
                        </li>
                    </ul>
                    <p v-if="(inspection.responsibles || []).length > 3" class="mt-3 text-xs font-semibold text-teal-700">+ {{ inspection.responsibles.length - 3 }} responsáveis no registro completo</p>
                    <div v-if="inspection.reference_documents?.length" class="mt-5 rounded-2xl border border-teal-100 bg-teal-50 p-3.5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-teal-700">Documento de referência</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ inspection.reference_documents[0].document.title }}</p>
                        <p class="mt-1 text-xs text-slate-500">Revisão {{ inspection.reference_documents[0].document.revision || '—' }}</p>
                    </div>
                </article>
            </section>

            <details class="group rounded-3xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Dados cadastrais e snapshot</h2>
                        <p class="mt-1 text-sm text-slate-500">Contexto congelado no planejamento da inspeção.</p>
                    </div>
                    <span class="text-xl text-slate-400 transition group-open:rotate-45">+</span>
                </summary>
                <div class="border-t border-slate-200 p-5 sm:p-6">
                    <InspectionSnapshot :snapshot="inspection.context_snapshot" :version="inspection.snapshot_version" />
                </div>
            </details>

            <details
                v-if="capabilities.assign_responsibles || capabilities.manage_references || (capabilities.transition && transitions.length)"
                class="group rounded-3xl border border-slate-200 bg-white shadow-sm"
            >
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Gestão da inspeção</h2>
                        <p class="mt-1 text-sm text-slate-500">Responsáveis, documentos e transições operacionais.</p>
                    </div>
                    <span class="text-xl text-slate-400 transition group-open:rotate-45">+</span>
                </summary>
                <div class="grid gap-6 border-t border-slate-200 p-5 lg:grid-cols-2 sm:p-6">
                    <section>
                        <h3 class="font-semibold text-slate-950">Responsáveis</h3>
                        <ul class="mt-4 divide-y divide-slate-100">
                            <li v-for="item in inspection.responsibles" :key="`${item.user.id}-${item.responsibility}`" class="flex items-start justify-between gap-3 py-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-slate-900">{{ item.user.name }}</span>
                                        <span v-if="item.is_primary" class="rounded-full bg-teal-50 px-2 py-1 text-[10px] font-bold uppercase text-teal-700">Principal</span>
                                    </div>
                                    <p class="text-sm text-slate-500">{{ item.responsibility_label }}</p>
                                </div>
                                <div v-if="capabilities.assign_responsibles" class="flex gap-2">
                                    <button v-if="!item.is_primary" type="button" class="text-xs font-semibold text-indigo-700" @click="setPrimaryResponsible(item.set_primary_url)">Principal</button>
                                    <button type="button" class="text-xs font-semibold text-rose-700" @click="removeResponsible(item.destroy_url)">Remover</button>
                                </div>
                            </li>
                        </ul>
                        <AssignmentForm
                            v-if="capabilities.assign_responsibles"
                            class="mt-4"
                            :action="capabilities.assign_responsibles.action"
                            :users="assignment_options.users"
                            :roles="assignment_options.roles"
                        />
                    </section>
                    <section>
                        <h3 class="font-semibold text-slate-950">Documentos de referência</h3>
                        <ReferenceDocumentsForm
                            v-if="capabilities.manage_references"
                            class="mt-4"
                            :action="capabilities.manage_references.action"
                            :documents="available_documents"
                            :selected-document-ids="inspection.reference_document_ids"
                        />
                    </section>
                    <section v-if="capabilities.transition && transitions.length" class="lg:col-span-2">
                        <h3 class="mb-4 font-semibold text-slate-950">Ações de fluxo</h3>
                        <div class="grid gap-4 md:grid-cols-2">
                            <TransitionForm v-for="transition in transitions" :key="transition.key" :transition="transition" />
                        </div>
                    </section>
                </div>
            </details>
        </div>

        <div v-else-if="active_tab === 'defects'" class="print-hidden mt-6 space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Leitura técnica</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Avarias da reinspeção</h2>
                        <p class="mt-1 text-sm text-slate-500">Filtre por prioridade e abra a avaliação sem perder o contexto.</p>
                    </div>
                    <div class="flex max-w-full gap-2 overflow-x-auto pb-1" role="group" aria-label="Filtrar avarias">
                        <button
                            v-for="filter in filters"
                            :key="filter.key"
                            type="button"
                            class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            :class="activeFilter === filter.key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            :aria-pressed="activeFilter === filter.key"
                            @click="activeFilter = filter.key"
                        >
                            {{ filter.label }}
                            <span class="rounded-full px-1.5 text-xs" :class="activeFilter === filter.key ? 'bg-white/15' : 'bg-white'">{{ filter.count }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <DefectCard v-for="defect in filteredDefects" :key="defect.id" :defect="defect" />
            </div>
            <div v-if="filteredDefects.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-500">
                Nenhuma avaria corresponde a este filtro.
            </div>

            <details v-if="capabilities.defects?.create" class="group rounded-3xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="font-semibold text-slate-950">Adicionar avaria</h2>
                        <p class="mt-1 text-sm text-slate-500">Ação operacional secundária nesta apresentação.</p>
                    </div>
                    <span class="text-xl text-slate-400 transition group-open:rotate-45">+</span>
                </summary>
                <div class="border-t border-slate-200 p-5">
                    <DefectCreateForm v-if="inspection.equipment.defect_code_prefix" :action="capabilities.defects.create.action" />
                    <p v-else class="text-sm text-amber-800">Configure o prefixo de avaria antes de criar um registro.</p>
                </div>
            </details>
        </div>

        <div v-else-if="active_tab === 'locations'" class="print-hidden mt-6 space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Localização técnica</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Mapa de localização das avarias</h2>
                        <p class="mt-1 text-sm text-slate-500">Planta, croqui ou foto anotada ficam como base estática nesta etapa; o editor gráfico vem depois.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span v-for="item in (content.legend || [])" :key="item.code" class="rounded-full bg-slate-100 px-3 py-1.5">
                            {{ item.code }} · {{ item.label }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(24rem,0.85fr)]">
                    <section class="rounded-3xl border border-slate-200 bg-slate-950 p-5 text-white shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-300">Planta / croqui</p>
                                <h3 class="mt-2 text-lg font-semibold">Marcadores referenciados ao desenho</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-300">Desenho {{ content.items?.[0]?.drawing || inspection.drawing || '—' }} · {{ content.items?.length || 0 }} marcador(es) carregados.</p>
                            </div>
                            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-200">
                                {{ content.items?.[0]?.project || inspection.service_order || '—' }}
                            </span>
                        </div>
                        <div class="mt-6 rounded-3xl border border-white/10 bg-white/5 p-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <article v-for="item in (content.items || [])" :key="item.id" class="rounded-2xl border border-white/10 bg-slate-900/60 p-3">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl bg-teal-400/20 px-2 text-sm font-bold text-teal-200">
                                            {{ item.marker }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-white">{{ item.title }}</p>
                                            <p class="mt-1 text-xs leading-5 text-slate-300">{{ item.location }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-300">
                                        <span class="rounded-full bg-white/10 px-2.5 py-1">{{ item.project }}</span>
                                        <span class="rounded-full bg-white/10 px-2.5 py-1">{{ item.element }}</span>
                                        <span class="rounded-full bg-white/10 px-2.5 py-1">{{ item.photo_count }} foto(s)</span>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </section>

                    <aside class="space-y-4">
                        <article v-for="item in (content.items || [])" :key="`${item.id}-summary`" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">{{ item.marker }}</p>
                                    <h3 class="mt-1 font-semibold text-slate-950">{{ item.title }}</h3>
                                </div>
                                <CivilClassificationBadge :code="item.classification?.code" :label="item.classification?.label" :historical="item.classification?.historical" />
                            </div>
                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Localização</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ item.location }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Impacto</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ item.impact?.label || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">GUT</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ item.gut?.score ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Fotos</dt>
                                    <dd class="mt-1 font-medium text-slate-900">{{ item.photo_count }} · {{ item.photo_interval }}</dd>
                                </div>
                            </dl>
                        </article>
                    </aside>
                </div>
            </section>
        </div>

        <div v-else-if="active_tab === 'photos'" class="print-hidden mt-6 space-y-6">
            <ProvisionalDataNotice :message="demo.photo_notice" />
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Evidências técnicas</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Galeria da inspeção</h2>
                        <p class="mt-1 text-sm text-slate-500">Placeholders neutros já preparados para receber os arquivos reais.</p>
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

        <div v-else-if="active_tab === 'documents'" class="print-hidden mt-6 space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Base técnica</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Documentos de referência</h2>
                    <p class="mt-1 text-sm text-slate-500">Arquivos congelados para o contexto desta inspeção.</p>
                </div>
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <article v-for="item in (content.items || [])" :key="item.id" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ item.document.document_type_label }}</p>
                        <h3 class="mt-2 font-semibold text-slate-950">{{ item.document.title }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Revisão {{ item.document.revision || '—' }} · {{ item.document.status_label }}</p>
                        <div class="mt-4 flex gap-3">
                            <Link :href="item.document.show_url" class="text-sm font-semibold text-teal-700">Abrir</Link>
                            <Link :href="item.document.download_url" class="text-sm font-semibold text-slate-700">Baixar</Link>
                        </div>
                    </article>
                </div>
                <p v-if="!(content.items || []).length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                    {{ content.empty_message }}
                </p>
            </section>
            <details v-if="capabilities.manage_references" class="group rounded-3xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between p-5">
                    <span class="font-semibold text-slate-950">Gerenciar documentos vinculados</span>
                    <span class="text-xl text-slate-400 transition group-open:rotate-45">+</span>
                </summary>
                <div class="border-t border-slate-200 p-5">
                    <ReferenceDocumentsForm
                        :action="capabilities.manage_references.action"
                        :documents="available_documents"
                        :selected-document-ids="content.reference_document_ids || []"
                    />
                </div>
            </details>
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
                <ProvisionalDataNotice class="max-w-3xl flex-1" title="Prévia de demonstração" message="O documento final ainda não foi gerado. Conteúdo e parâmetros técnicos permanecem provisórios." compact />
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
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Concluídas</p>
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

                    <ReportSection index="01" title="Aspectos gerais" content-class="mt-5">
                        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div v-for="item in reportGeneralAspects" :key="item.label" class="rounded-2xl border border-slate-200 bg-white p-4">
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ item.label }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</dd>
                            </div>
                        </dl>
                    </ReportSection>

                    <ReportSection index="02" title="Mapa de localização" content-class="mt-5 space-y-4">
                        <div class="grid gap-4 xl:grid-cols-2">
                            <article v-for="location in reportLocations" :key="location.id" class="break-inside-avoid rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-teal-700">{{ location.marker }}</p>
                                        <h4 class="mt-1 font-semibold text-slate-950">{{ location.title }}</h4>
                                    </div>
                                    <CivilClassificationBadge :code="location.classification?.code" :historical="location.classification?.historical" />
                                </div>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ location.location }}</p>
                                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Elemento</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ location.element }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Impacto</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ location.impact?.label || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">GUT</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ location.gut?.score ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-400">Fotos</dt>
                                        <dd class="mt-1 font-medium text-slate-900">{{ location.photo_count }} · {{ location.photo_interval }}</dd>
                                    </div>
                                </dl>
                            </article>
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
                                <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2 py-1 text-[10px] font-bold uppercase text-slate-700">{{ photo.role_label || 'Ilustrativa' }}</span>
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

                    <section class="grid gap-8 sm:grid-cols-2">
                        <ReportSection index="06" title="Responsabilidade técnica" content-class="mt-4">
                            <ul class="space-y-3">
                                <li v-for="item in reportResponsibles" :key="`${item.user.id}-${item.responsibility}`">
                                    <p class="font-medium text-slate-900">{{ item.user.name }}</p>
                                    <p class="text-sm text-slate-500">{{ item.responsibility_label }}</p>
                                </li>
                            </ul>
                        </ReportSection>
                        <ReportSection index="07" title="Documentos de referência" content-class="mt-4">
                            <ul class="space-y-3">
                                <li v-for="item in reportDocuments" :key="item.id">
                                    <p class="font-medium text-slate-900">{{ item.document.title }}</p>
                                    <p class="text-sm text-slate-500">Revisão {{ item.document.revision || '—' }}</p>
                                </li>
                            </ul>
                        </ReportSection>
                    </section>
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-6 py-5 text-xs text-slate-500 sm:px-10">
                    <span>{{ content.cover?.provider || 'Vistoria Serviços de Inspeção Ltda.' }}</span>
                    <span>{{ content.revision }} · Prévia de demonstração</span>
                </footer>
            </article>
        </div>
    </AppLayout>
</template>

<style>
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

    .report-document {
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        max-width: none !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .break-inside-avoid {
        break-inside: avoid;
    }
}
</style>
