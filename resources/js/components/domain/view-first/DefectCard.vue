<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DefectConditionBadge from '@/components/domain/defects/DefectConditionBadge.vue';
import DefectAssessmentStatusBadge from '@/components/domain/defects/DefectAssessmentStatusBadge.vue';
import CivilClassificationBadge from './CivilClassificationBadge.vue';

const props = defineProps({
    defect: {
        type: Object,
        required: true,
    },
});

const assessment = computed(() => props.defect.current_assessment ?? props.defect.assessment ?? {});
const condition = computed(() => assessment.value.condition ?? props.defect.condition ?? 'unchanged');
const status = computed(() => assessment.value.status ?? props.defect.assessment_status ?? 'draft');
const classification = computed(() => props.defect.classification ?? {});
const gut = computed(() => props.defect.gut ?? {});
const discipline = computed(() => props.defect.discipline_label ?? 'Civil');
const project = computed(() => props.defect.project ?? '—');
const drawing = computed(() => props.defect.drawing ?? '—');
const item = computed(() => props.defect.item ?? '—');
const photoInterval = computed(() => props.defect.photo_interval ?? '—');
const quantitySummary = computed(() => props.defect.quantity_summary?.total_label ?? '—');
const manifestation = computed(() => props.defect.manifestation ?? '—');
const impact = computed(() => props.defect.impact?.label ?? 'Sem impacto direto');
const location = computed(() => assessment.value.location_description ?? props.defect.location ?? props.defect.location_description ?? 'Localização não informada');
const evidenceCount = computed(() => (
    Array.isArray(props.defect.evidence)
        ? props.defect.evidence.length
        : (props.defect.evidence?.count ?? props.defect.photos_count ?? props.defect.photo_count ?? 0)
));
const actionUrl = computed(() => props.defect.assessment_url ?? props.defect.show_url ?? null);
const element = computed(() => {
    if (props.defect.element) {
        return props.defect.element;
    }

    if (Array.isArray(props.defect.characterization)) {
        return props.defect.characterization.find((entry) => entry.label === 'Elemento')?.value ?? '—';
    }

    return props.defect.characterization?.element ?? '—';
});
</script>

<template>
    <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">{{ defect.code }}</span>
                    <DefectConditionBadge :condition="condition" />
                    <DefectAssessmentStatusBadge :status="status" />
                </div>
                <h3 class="mt-3 text-lg font-semibold leading-snug text-slate-950">{{ defect.title }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ location }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold text-slate-500">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ discipline }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ project }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1">{{ drawing }}</span>
                </div>
            </div>

            <CivilClassificationBadge :code="classification.code" :label="classification.label" :historical="classification.historical" large />
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-slate-50 px-3.5 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pontuação GUT</div>
                <div class="mt-1 font-semibold text-slate-900">{{ gut.score ?? gut.gut_score ?? 'Não aplicável' }}</div>
                <div class="mt-0.5 text-xs text-slate-500">{{ classification.score_band || '—' }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3.5 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Quantitativo</div>
                <div class="mt-1 font-semibold text-slate-900">{{ quantitySummary }}</div>
                <div class="mt-0.5 text-xs text-slate-500">{{ item }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3.5 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Fotos</div>
                <div class="mt-1 font-semibold text-slate-900">{{ evidenceCount }} item(ns)</div>
                <div class="mt-0.5 text-xs text-slate-500">{{ photoInterval }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 px-3.5 py-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Elemento</div>
                <div class="mt-1 truncate font-semibold text-slate-900">{{ element }}</div>
                <div class="mt-0.5 text-xs text-slate-500">{{ manifestation }} · {{ impact }}</div>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
            <p v-if="defect.pending_label || defect.is_pending" class="text-sm font-medium text-amber-700">{{ defect.pending_label || 'Avaliação pendente' }}</p>
            <span v-else class="text-sm text-slate-500">Dados técnicos consolidados</span>
            <Link
                v-if="actionUrl"
                :href="actionUrl"
                class="inline-flex min-h-10 items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
            >
                {{ status === 'draft' ? 'Continuar avaliação' : 'Ver avaliação' }}
                <span class="ml-2" aria-hidden="true">→</span>
            </Link>
        </div>
    </article>
</template>
