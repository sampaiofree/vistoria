<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AssignmentForm from '@/components/domain/inspections/AssignmentForm.vue';
import DefectAssessmentForm from '@/components/domain/defects/DefectAssessmentForm.vue';
import DefectCreateForm from '@/components/domain/defects/DefectCreateForm.vue';
import PreviousAssessmentCard from '@/components/domain/defects/PreviousAssessmentCard.vue';
import DefectStatusBadge from '@/components/domain/defects/DefectStatusBadge.vue';
import InspectionSnapshot from '@/components/domain/inspections/InspectionSnapshot.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTimeline from '@/components/domain/inspections/InspectionTimeline.vue';
import ReferenceDocumentsForm from '@/components/domain/inspections/ReferenceDocumentsForm.vue';
import TransitionForm from '@/components/domain/inspections/TransitionForm.vue';

defineProps({
    inspection: { type: Object, required: true },
    capabilities: { type: Object, required: true },
    assignment_options: { type: Object, default: () => ({ users: [], roles: [] }) },
    available_documents: { type: Array, default: () => [] },
    transitions: { type: Array, default: () => [] },
    index_url: { type: String, required: true },
});

function setPrimaryResponsible(url) {
    router.patch(url, {}, { preserveScroll: true });
}

function removeResponsible(url) {
    if (!window.confirm('Remover este responsável?')) {
        return;
    }

    router.delete(url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="inspection.number" :subtitle="`${inspection.equipment.tag} — ${inspection.equipment.name}`">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="index_url" class="text-sm font-semibold text-teal-700">← Voltar às inspeções</Link>
                <Link
                    v-if="capabilities.update_planned"
                    :href="capabilities.update_planned.action"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                >
                    Editar planejamento
                </Link>
            </div>
            <InspectionStatusBadge :status="inspection.status" />
        </div>

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <div class="text-xs uppercase text-slate-500">Tipo</div>
                <div class="font-semibold">{{ inspection.type === 'reinspection' ? 'Reinspeção' : 'Inicial' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-slate-500">Planejada</div>
                <div class="font-semibold">{{ inspection.scheduled_at || '—' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-slate-500">Inspeção anterior</div>
                <Link v-if="inspection.previous_inspection" :href="inspection.previous_inspection.show_url" class="font-semibold text-teal-700">
                    {{ inspection.previous_inspection.number }}
                </Link>
                <div v-else>—</div>
            </div>
            <div>
                <div class="text-xs uppercase text-slate-500">Estado atual</div>
                <InspectionStatusBadge class="mt-1" :status="inspection.status" />
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Dados de planejamento</h2>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <div class="text-xs uppercase text-slate-500">Cliente</div>
                    <div class="font-semibold">{{ inspection.equipment.client?.name || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Unidade</div>
                    <div class="font-semibold">{{ inspection.equipment.unit?.name || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">O.S.</div>
                    <div class="font-semibold">{{ inspection.service_order || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Relatório externo</div>
                    <div class="font-semibold">{{ inspection.external_report_number || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Procedimento</div>
                    <div class="font-semibold">{{ inspection.procedure_number || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Classificação atmosférica</div>
                    <div class="font-semibold">{{ inspection.atmospheric_classification || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Planejada para</div>
                    <div class="font-semibold">{{ inspection.scheduled_at || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Inspecionada em</div>
                    <div class="font-semibold">{{ inspection.inspected_on || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-slate-500">Inspeção anterior</div>
                    <Link
                        v-if="inspection.previous_inspection"
                        :href="inspection.previous_inspection.show_url"
                        class="font-semibold text-teal-700"
                    >
                        {{ inspection.previous_inspection.number }}
                    </Link>
                    <div v-else>—</div>
                </div>
            </div>

            <div v-if="inspection.general_notes" class="mt-4 rounded-xl bg-slate-50 p-4">
                <div class="text-xs uppercase text-slate-500">Observações gerais</div>
                <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ inspection.general_notes }}</p>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Snapshot da inspeção</h2>
            <InspectionSnapshot :snapshot="inspection.context_snapshot" :version="inspection.snapshot_version" />
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Avarias</h2>
                    <p class="text-sm text-slate-500">
                        A identidade da avaria permanece ao longo das reinspeções.
                    </p>
                </div>
                <div v-if="capabilities.defects?.create" class="text-sm text-slate-500">
                    Nova avaria nesta inspeção
                </div>
            </div>

            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                <div>
                    <ul class="space-y-3">
                        <li
                            v-for="defect in (inspection.defects || [])"
                            :key="defect.id"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <Link :href="defect.show_url" class="font-semibold text-teal-700 hover:text-teal-800">
                                        {{ defect.code }}
                                    </Link>
                                    <p class="text-sm text-slate-500">{{ defect.title }}</p>
                                    <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">
                                        Categoria {{ defect.category_label }}
                                    </p>
                                </div>
                                <DefectStatusBadge :status="defect.status" />
                            </div>

                            <div
                                v-if="defect.assessment_actions.store_url || defect.assessment_actions.update_url || defect.assessment_actions.complete_url"
                                class="mt-4"
                            >
                                <DefectAssessmentForm
                                    :assessment="defect.current_assessment"
                                    :previous-assessment="defect.current_assessment ? defect.previous_assessment : defect.latest_assessment"
                                    :store-action="defect.assessment_actions.store_url"
                                    :update-action="defect.assessment_actions.update_url"
                                    :complete-action="defect.assessment_actions.complete_url"
                                    :allow-new-condition="false"
                                    title="Avaliação da inspeção"
                                    :note="defect.current_assessment
                                        ? (defect.current_assessment.status === 'draft'
                                            ? 'Finalize ou ajuste o rascunho desta avaria.'
                                            : 'Revise a avaliação atual desta avaria.')
                                        : 'Registre a condição desta avaria nesta inspeção.'"
                                />
                            </div>

                            <div v-else-if="defect.latest_assessment" class="mt-4">
                                <PreviousAssessmentCard
                                    :assessment="defect.latest_assessment"
                                    title="Estado atual"
                                />
                            </div>
                        </li>
                        <li v-if="(inspection.defects || []).length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                            Nenhuma avaria registrada nesta inspeção.
                        </li>
                    </ul>
                </div>

                <div>
                    <div
                        v-if="capabilities.defects?.create && inspection.equipment.defect_code_prefix"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <DefectCreateForm :action="capabilities.defects.create.action" />
                    </div>

                    <div
                        v-else-if="capabilities.defects?.create"
                        class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                    >
                        Configure o prefixo de avaria no cadastro do equipamento antes de criar a primeira avaria.
                    </div>
                </div>
            </div>
        </section>

        <section v-if="inspection.next_inspections.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Reinspeções vinculadas</h2>
            <ul class="space-y-3">
                <li
                    v-for="nextInspection in inspection.next_inspections"
                    :key="nextInspection.id"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <Link :href="nextInspection.show_url" class="font-semibold text-teal-700 hover:text-teal-800">
                                {{ nextInspection.number }}
                            </Link>
                            <p class="text-sm text-slate-500">
                                {{ nextInspection.inspection_type_label }} · liberada em {{ nextInspection.released_at || '—' }}
                            </p>
                        </div>
                        <InspectionStatusBadge :status="nextInspection.status" />
                    </div>
                </li>
            </ul>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Responsáveis</h2>

                <ul class="mb-5 divide-y divide-slate-100">
                    <li v-for="item in inspection.responsibles" :key="`${item.user.id}-${item.responsibility}`" class="py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ item.user.name }}</span>
                                    <span
                                        v-if="item.is_primary"
                                        class="inline-flex items-center rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-teal-700"
                                    >
                                        Principal
                                    </span>
                                </div>
                                <div class="text-sm text-slate-500">{{ item.responsibility_label }}</div>
                                <div class="text-xs text-slate-400">
                                    Atribuído em {{ item.assigned_at ?? '—' }}
                                    <span v-if="item.completed_at"> · Concluído em {{ item.completed_at }}</span>
                                </div>
                            </div>

                            <div v-if="capabilities.assign_responsibles" class="flex flex-wrap gap-2">
                                <button
                                    v-if="!item.is_primary"
                                    type="button"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100"
                                    @click="setPrimaryResponsible(item.set_primary_url)"
                                >
                                    Tornar principal
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100"
                                    @click="removeResponsible(item.destroy_url)"
                                >
                                    Remover
                                </button>
                            </div>
                        </div>
                    </li>
                    <li v-if="inspection.responsibles.length === 0" class="py-3 text-sm text-slate-500">Nenhum responsável atribuído.</li>
                </ul>

                <AssignmentForm
                    v-if="capabilities.assign_responsibles"
                    :action="capabilities.assign_responsibles.action"
                    :users="assignment_options.users"
                    :roles="assignment_options.roles"
                />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Documentos de referência</h2>

                <ul class="mb-5 space-y-2">
                    <li v-for="document in inspection.reference_documents" :key="document.id" class="rounded-lg bg-slate-50 p-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <Link :href="document.document.show_url" class="font-semibold text-teal-700 hover:text-teal-800">
                                    {{ document.document.title }}
                                </Link>
                                <div class="text-slate-500">
                                    Revisão {{ document.document.revision ?? '—' }} · {{ document.document.status_label }}
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{ document.document.document_type_label }}
                                    <span v-if="document.added_by"> · Selecionado por {{ document.added_by.name }}</span>
                                    <span v-if="document.created_at"> · {{ document.created_at }}</span>
                                </div>
                            </div>

                            <Link
                                :href="document.document.download_url"
                                class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-100"
                            >
                                Baixar
                            </Link>
                        </div>
                    </li>
                    <li v-if="inspection.reference_documents.length === 0" class="text-sm text-slate-500">Nenhum documento selecionado.</li>
                </ul>

                <ReferenceDocumentsForm
                    v-if="capabilities.manage_references"
                    :action="capabilities.manage_references.action"
                    :documents="available_documents"
                    :selected-document-ids="inspection.reference_document_ids"
                />
            </section>
        </div>

        <section v-if="capabilities.transition && transitions.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-semibold">Ações disponíveis</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <TransitionForm v-for="transition in transitions" :key="transition.key" :transition="transition" />
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-5 text-lg font-semibold">Histórico</h2>
            <InspectionTimeline :history="inspection.history" />
        </section>
    </AppLayout>
</template>
