<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectAssessmentForm from '@/components/domain/defects/DefectAssessmentForm.vue';
import DefectHistoryTimeline from '@/components/domain/defects/DefectHistoryTimeline.vue';
import DefectStatusBadge from '@/components/domain/defects/DefectStatusBadge.vue';
import PreviousAssessmentCard from '@/components/domain/defects/PreviousAssessmentCard.vue';

defineProps({
    defect: {
        type: Object,
        required: true,
    },
    assessments: {
        type: Array,
        default: () => [],
    },
    back_url: {
        type: String,
        required: true,
    },
    equipment_url: {
        type: String,
        required: true,
    },
    inspection_url: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        :title="defect.code"
        :subtitle="`${defect.equipment.tag} — ${defect.equipment.name}`"
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-semibold text-slate-900">{{ defect.title }}</h2>
                        <DefectStatusBadge :status="defect.status" />
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        <Link :href="back_url" class="font-medium text-teal-700 hover:text-teal-800">← Voltar à inspeção</Link>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="equipment_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Ver equipamento
                    </Link>
                    <Link
                        :href="inspection_url"
                        class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-100"
                    >
                        Ver inspeção
                    </Link>
                </div>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ defect.code }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categoria</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ defect.category_label }}</dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1">
                        <DefectStatusBadge :status="defect.status" />
                    </dd>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sequência</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ defect.sequence_number }}</dd>
                </div>
            </dl>

            <div v-if="defect.origin_description" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Origem</div>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ defect.origin_description }}</p>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-6">
                <DefectAssessmentForm
                    v-if="defect.assessment_actions.update_url || defect.assessment_actions.complete_url"
                    :assessment="defect.current_assessment || defect.latest_assessment"
                    :previous-assessment="defect.previous_assessment"
                    :update-action="defect.assessment_actions.update_url"
                    :complete-action="defect.assessment_actions.complete_url"
                    :allow-new-condition="defect.latest_assessment?.inspection?.id === defect.first_inspection.id"
                    title="Editar avaliação"
                        :note="defect.current_assessment?.status === 'draft'
                            ? 'Finalize ou ajuste o rascunho desta avaliação.'
                            : 'Salvar rascunho reabre a avaliação para edição.'"
                />

                <PreviousAssessmentCard
                    v-else
                    :assessment="defect.latest_assessment"
                    title="Avaliação atual"
                />
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Histórico</h3>
                        <p class="text-sm text-slate-500">
                            Todas as avaliações concluídas desta avaria.
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <DefectHistoryTimeline :assessments="assessments" />
                </div>
            </section>
        </section>
    </AppLayout>
</template>
