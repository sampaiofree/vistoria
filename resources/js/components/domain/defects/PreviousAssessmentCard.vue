<script setup>
import { Link } from '@inertiajs/vue3';
import DefectAssessmentStatusBadge from './DefectAssessmentStatusBadge.vue';
import DefectConditionBadge from './DefectConditionBadge.vue';

defineProps({
    assessment: {
        type: Object,
        default: null,
    },
    title: {
        type: String,
        default: 'Avaliação anterior',
    },
});
</script>

<template>
    <div v-if="assessment" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ title }}</div>
                <Link
                    :href="assessment.inspection.show_url"
                    class="mt-1 block text-sm font-semibold text-teal-700 hover:text-teal-800"
                >
                    {{ assessment.inspection.number || '—' }}
                </Link>
            </div>

            <div class="flex flex-wrap gap-2">
                <DefectConditionBadge :condition="assessment.condition" />
                <DefectAssessmentStatusBadge :status="assessment.status" />
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-xs uppercase text-slate-500">Condição</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ assessment.condition_label }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3">
                <div class="text-xs uppercase text-slate-500">Concluída em</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">{{ assessment.assessed_at || '—' }}</div>
            </div>
        </div>

        <div class="mt-4 space-y-3 text-sm text-slate-700">
            <p v-if="assessment.comment" class="whitespace-pre-line">
                <span class="font-semibold text-slate-900">Comentário:</span> {{ assessment.comment }}
            </p>
            <p v-if="assessment.recommendation" class="whitespace-pre-line">
                <span class="font-semibold text-slate-900">Recomendação:</span> {{ assessment.recommendation }}
            </p>
            <p v-if="assessment.reason" class="whitespace-pre-line">
                <span class="font-semibold text-slate-900">Justificativa:</span> {{ assessment.reason }}
            </p>
        </div>
    </div>

    <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
        Nenhuma avaliação anterior registrada.
    </div>
</template>
