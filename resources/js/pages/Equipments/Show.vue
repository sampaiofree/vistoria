<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import UiIcon from '@/components/ui/UiIcon.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import EquipmentStatusActions from '@/components/domain/equipments/EquipmentStatusActions.vue';
import EquipmentDocumentUpload from '@/components/domain/equipments/EquipmentDocumentUpload.vue';
import EquipmentDocumentList from '@/components/domain/equipments/EquipmentDocumentList.vue';
import EquipmentMetricCard from '@/components/domain/equipments/EquipmentMetricCard.vue';
import EquipmentInspectionHistory from '@/components/domain/equipments/EquipmentInspectionHistory.vue';
import AssessmentProgress from '@/components/domain/view-first/AssessmentProgress.vue';

const props = defineProps({
    equipment: {
        type: Object,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    unit: {
        type: Object,
        required: true,
    },
    area: {
        type: Object,
        required: true,
    },
    subarea: {
        type: Object,
        default: null,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    document_types: {
        type: Array,
        required: true,
    },
    executive_summary: {
        type: Object,
        default: () => ({
            criticality: null,
            active_defects: 0,
            inspections: 0,
            current_documents: 0,
        }),
    },
    current_inspection: {
        type: Object,
        default: null,
    },
    inspection_history: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        required: true,
    },
    index_url: {
        type: String,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
    edit_url: {
        type: String,
        required: true,
    },
    status_url: {
        type: String,
        required: true,
    },
    document_store_url: {
        type: String,
        required: true,
    },
});

const criticality = computed(() => props.executive_summary?.criticality ?? null);
const currentProgress = computed(() => props.current_inspection?.progress ?? { completed: 0, total: 0, percentage: 0 });
const currentDocuments = computed(() => props.documents.filter((document) => document.is_current));
const currentInspectionAction = computed(() => (
    props.current_inspection?.inspection_type === 'reinspection'
    && props.current_inspection?.status === 'in_progress'
        ? 'Continuar reinspeção'
        : 'Abrir inspeção atual'
));

const registrationItems = computed(() => [
    ['Fabricante', props.equipment.manufacturer],
    ['Modelo', props.equipment.model],
    ['Número de série', props.equipment.serial_number],
    ['Código patrimonial', props.equipment.asset_code],
    ['Código ABC', props.equipment.abc_code],
    ['Comissionamento', props.equipment.commissioned_at],
    ['Prefixo de avaria', props.equipment.defect_code_prefix],
]);
</script>

<template>
    <AppLayout
        title="Visão do equipamento"
        subtitle="Situação técnica, inspeções e documentos do ativo em uma única visão."
        wide
    >
        <template #actions>
            <Link
                :href="index_url"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
            >
                Voltar aos equipamentos
            </Link>
        </template>

        <section class="relative isolate overflow-hidden rounded-3xl bg-[#081a2f] p-5 text-white shadow-xl shadow-slate-950/10 sm:p-7 lg:p-8">
            <div class="pointer-events-none absolute -right-20 -top-36 h-80 w-80 rounded-full border border-teal-300/20 bg-teal-400/10" />
            <div class="pointer-events-none absolute -bottom-52 right-28 h-80 w-80 rounded-full border border-white/10" />

            <div class="relative flex min-w-0 flex-col gap-8 xl:flex-row xl:items-end xl:justify-between">
                <div class="min-w-0">
                    <nav aria-label="Estrutura do equipamento" class="flex flex-wrap gap-x-2 gap-y-1 text-xs font-medium text-slate-300">
                        <Link :href="client.show_url" class="transition hover:text-teal-200">{{ client.name }}</Link>
                        <span aria-hidden="true">/</span>
                        <Link :href="unit.show_url" class="transition hover:text-teal-200">{{ unit.name }}</Link>
                        <span aria-hidden="true">/</span>
                        <Link :href="area.show_url" class="transition hover:text-teal-200">{{ area.name }}</Link>
                        <template v-if="subarea">
                            <span aria-hidden="true">/</span>
                            <Link :href="subarea.show_url" class="transition hover:text-teal-200">{{ subarea.name }}</Link>
                        </template>
                    </nav>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <span class="rounded-lg border border-teal-300/20 bg-teal-300/10 px-3 py-1.5 text-xs font-semibold tracking-[0.12em] text-teal-200">
                            TAG {{ equipment.tag }}
                        </span>
                        <StatusBadge :status="equipment.status" />
                    </div>
                    <h2 class="mt-3 max-w-4xl text-3xl font-semibold tracking-tight sm:text-4xl">{{ equipment.name }}</h2>
                    <p v-if="equipment.installation_location" class="mt-3 flex items-start gap-2 text-sm leading-6 text-slate-300">
                        <UiIcon name="equipments" class="mt-0.5 h-4 w-4 shrink-0 text-teal-300" />
                        <span>{{ equipment.installation_location }}</span>
                    </p>
                </div>

                <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-end">
                    <div v-if="criticality" class="min-w-40 rounded-2xl border border-white/10 bg-white/5 px-5 py-4 backdrop-blur-sm">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-300">Criticidade CIVIL</p>
                        <div class="mt-2 flex items-end gap-2">
                            <strong class="text-3xl font-semibold text-teal-200">{{ criticality.value }}</strong>
                            <span v-if="criticality.label" class="pb-1 text-xs text-slate-300">{{ criticality.label }}</span>
                        </div>
                        <p v-if="criticality.is_provisional" class="mt-2 text-[0.68rem] text-slate-400">Classificação demonstrativa</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Link
                            v-if="current_inspection?.show_url"
                            :href="current_inspection.show_url"
                            class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-teal-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-teal-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-200"
                        >
                            {{ currentInspectionAction }}
                            <UiIcon name="arrow-right" class="h-4 w-4" />
                        </Link>

                        <details v-if="can.update || can.create || can.change_status" class="group relative">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-white/30 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/40 [&::-webkit-details-marker]:hidden">
                                Mais ações
                                <UiIcon name="chevron-right" class="h-4 w-4 transition group-open:rotate-90" />
                            </summary>
                            <div class="mt-2 min-w-64 rounded-2xl border border-slate-200 bg-white p-3 text-slate-900 shadow-xl">
                                <div class="grid gap-2">
                                    <Link v-if="can.update" :href="edit_url" class="rounded-xl px-3 py-2.5 text-sm font-medium transition hover:bg-slate-100">
                                        Editar cadastro
                                    </Link>
                                    <Link v-if="can.create" :href="create_url" class="rounded-xl px-3 py-2.5 text-sm font-medium transition hover:bg-slate-100">
                                        Novo equipamento
                                    </Link>
                                    <div v-if="can.change_status" class="border-t border-slate-200 pt-3">
                                        <EquipmentStatusActions
                                            :action="status_url"
                                            :current-status="equipment.status"
                                            entity-label="equipamento"
                                        />
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-5 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores do equipamento">
            <EquipmentMetricCard
                label="Criticidade atual"
                :value="criticality?.value ?? '—'"
                :caption="criticality ? (criticality.is_provisional ? 'Parâmetro demonstrativo' : criticality.label) : 'Sem classificação disponível'"
                :tone="criticality?.value === 'CV-2' ? 'critical' : 'default'"
            />
            <EquipmentMetricCard
                label="Avarias ativas"
                :value="executive_summary.active_defects ?? 0"
                caption="Condições em acompanhamento"
                tone="teal"
            />
            <EquipmentMetricCard
                label="Inspeções"
                :value="executive_summary.inspections ?? 0"
                caption="Ciclos registrados no histórico"
            />
            <EquipmentMetricCard
                label="Documentos atuais"
                :value="executive_summary.current_documents ?? currentDocuments.length"
                caption="Arquivos técnicos vigentes"
                tone="navy"
            />
        </section>

        <div class="mt-6 grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,0.8fr)]">
            <div class="min-w-0 space-y-6">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Ciclo atual</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">Inspeção atual</h3>
                        </div>
                        <InspectionStatusBadge v-if="current_inspection" :status="current_inspection.status" />
                    </div>

                    <div v-if="current_inspection" class="p-5 sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <Link :href="current_inspection.show_url" class="text-xl font-semibold tracking-tight text-slate-950 transition hover:text-teal-700">
                                    {{ current_inspection.number }}
                                </Link>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ current_inspection.inspection_type_label }}
                                    <span v-if="current_inspection.service_order"> · OS {{ current_inspection.service_order }}</span>
                                </p>
                            </div>
                            <Link :href="current_inspection.show_url" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Abrir central da inspeção
                                <UiIcon name="arrow-right" class="h-4 w-4" />
                            </Link>
                        </div>

                        <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data programada</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ current_inspection.scheduled_for ?? '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data da inspeção</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ current_inspection.inspected_on ?? 'Em andamento' }}</dd>
                            </div>
                        </dl>

                        <div v-if="currentProgress.total > 0" class="mt-6">
                            <AssessmentProgress :progress="currentProgress" label="Avaliações preenchidas" />
                        </div>
                    </div>

                    <div v-else class="p-5 sm:p-6">
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <p class="text-sm font-medium text-slate-700">Não há uma inspeção aberta para este equipamento.</p>
                            <p class="mt-1 text-xs text-slate-500">Um novo ciclo aparecerá aqui após o planejamento.</p>
                        </div>
                    </div>
                </section>

                <EquipmentInspectionHistory :inspections="inspection_history" />
            </div>

            <aside class="min-w-0 space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Cadastro técnico</p>
                    <h3 class="mt-1 text-lg font-semibold text-slate-950">Dados do ativo</h3>

                    <dl class="mt-5 divide-y divide-slate-100">
                        <div v-for="item in registrationItems" :key="item[0]" class="grid grid-cols-[minmax(7rem,0.8fr)_minmax(0,1fr)] gap-3 py-3 first:pt-0 last:pb-0">
                            <dt class="text-xs font-medium text-slate-500">{{ item[0] }}</dt>
                            <dd class="break-words text-right text-sm font-semibold text-slate-800">{{ item[1] ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="equipment.description || equipment.notes" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold text-slate-950">Contexto do equipamento</h3>
                    <div v-if="equipment.description" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ equipment.description }}</p>
                    </div>
                    <div v-if="equipment.notes" class="mt-5 border-t border-slate-100 pt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observações</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ equipment.notes }}</p>
                    </div>
                </section>

                <section v-if="equipment.status === 'decommissioned'" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <h3 class="font-semibold text-amber-900">Equipamento descomissionado</h3>
                    <p v-if="equipment.decommissioned_at" class="mt-2 text-sm text-amber-800">Em {{ equipment.decommissioned_at }}</p>
                    <p v-if="equipment.decommission_reason" class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-900">{{ equipment.decommission_reason }}</p>
                </section>
            </aside>
        </div>

        <section class="mt-6 min-w-0">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-700">Base documental</p>
                    <h3 class="mt-1 text-xl font-semibold text-slate-950">Documentos técnicos</h3>
                    <p class="mt-1 text-sm text-slate-500">Arquivos privados e revisões vinculadas a este equipamento.</p>
                </div>

                <details v-if="can.manage_documents" class="group sm:w-auto">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-sm font-semibold text-teal-800 transition hover:bg-teal-100 [&::-webkit-details-marker]:hidden">
                        Gerenciar documentos
                        <UiIcon name="chevron-right" class="h-4 w-4 transition group-open:rotate-90" />
                    </summary>
                    <div class="mt-3 sm:min-w-[32rem]">
                        <EquipmentDocumentUpload
                            :action="document_store_url"
                            :document-types="document_types"
                            :documents="documents"
                        />
                    </div>
                </details>
            </div>

            <EquipmentDocumentList
                :documents="documents"
                :can-manage="can.manage_documents"
            />
        </section>
    </AppLayout>
</template>
