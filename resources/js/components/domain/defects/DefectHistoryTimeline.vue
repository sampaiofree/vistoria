<script setup>
import { Link } from '@inertiajs/vue3';
import DefectAssessmentStatusBadge from './DefectAssessmentStatusBadge.vue';
import DefectConditionBadge from './DefectConditionBadge.vue';

defineProps({
    assessments: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <ol class="space-y-4 border-l-2 border-slate-200 pl-5">
        <li
            v-for="assessment in assessments"
            :key="assessment.id"
            class="relative"
        >
            <span class="absolute -left-[1.65rem] top-1.5 h-3 w-3 rounded-full bg-teal-600 ring-4 ring-white"></span>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <DefectConditionBadge :condition="assessment.condition" />
                            <DefectAssessmentStatusBadge :status="assessment.status" />
                        </div>
                        <Link
                            :href="assessment.inspection.show_url"
                            class="mt-2 block text-sm font-semibold text-teal-700 hover:text-teal-800"
                        >
                            {{ assessment.inspection.number || '—' }}
                        </Link>
                    </div>
                    <div class="text-xs text-slate-400">
                        {{ assessment.assessed_at || '—' }}
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
                    <p v-if="assessment.internal_notes" class="whitespace-pre-line text-xs text-slate-500">
                        <span class="font-semibold text-slate-600">Notas internas:</span> {{ assessment.internal_notes }}
                    </p>
                </div>
            </div>
        </li>

        <li v-if="assessments.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
            Nenhuma avaliação concluída ainda.
        </li>
    </ol>
</template>
