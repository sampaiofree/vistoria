<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DefectAssessmentStatusBadge from './DefectAssessmentStatusBadge.vue';
import DefectConditionBadge from './DefectConditionBadge.vue';
import PreviousAssessmentCard from './PreviousAssessmentCard.vue';

const props = defineProps({
    assessment: {
        type: Object,
        default: null,
    },
    previousAssessment: {
        type: Object,
        default: null,
    },
    storeAction: {
        type: String,
        default: null,
    },
    updateAction: {
        type: String,
        default: null,
    },
    completeAction: {
        type: String,
        default: null,
    },
    allowNewCondition: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Avaliação',
    },
    note: {
        type: String,
        default: '',
    },
});

const allConditions = [
    { value: 'new', label: 'Nova' },
    { value: 'unchanged', label: 'Igual' },
    { value: 'worsened', label: 'Agravou' },
    { value: 'improved', label: 'Melhorou' },
    { value: 'repaired', label: 'Reparada' },
    { value: 'not_located', label: 'Não localizada' },
    { value: 'not_inspected', label: 'Não inspecionada' },
];

const conditionOptions = computed(() => (
    props.allowNewCondition
        ? allConditions
        : allConditions.filter((condition) => condition.value !== 'new')
));

const isCompleteAssessment = computed(() => props.assessment?.status === 'complete');
const canSaveDraft = computed(() => props.storeAction !== null || props.updateAction !== null);
const canComplete = computed(() => {
    if (isCompleteAssessment.value && props.updateAction !== null) {
        return false;
    }

    return props.completeAction !== null || props.storeAction !== null;
});

const form = useForm({
    condition: props.assessment?.condition ?? 'unchanged',
    location_description: props.assessment?.location_description ?? '',
    comment: props.assessment?.comment ?? '',
    recommendation: props.assessment?.recommendation ?? '',
    reason: props.assessment?.reason ?? '',
    internal_notes: props.assessment?.internal_notes ?? '',
    assessment_action: 'draft',
});

const labelClass = 'text-sm font-medium text-slate-700';
const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const helpClass = 'mt-1 text-xs text-rose-600';

const currentAssessmentSummary = computed(() => props.assessment ?? null);

function submitDraft() {
    if (props.updateAction) {
        form.patch(props.updateAction, {
            preserveScroll: true,
        });
        return;
    }

    if (props.storeAction) {
        form.assessment_action = 'draft';
        form.post(props.storeAction, {
            preserveScroll: true,
        });
    }
}

function submitComplete() {
    if (props.completeAction) {
        form.post(props.completeAction, {
            preserveScroll: true,
        });
        return;
    }

    if (props.storeAction) {
        form.assessment_action = 'complete';
        form.post(props.storeAction, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
                <p v-if="note" class="mt-1 text-sm text-slate-500">{{ note }}</p>
                <p v-else-if="!assessment" class="mt-1 text-sm text-slate-500">
                    A avaliação será iniciada como rascunho nesta inspeção.
                </p>
                <p v-else-if="isCompleteAssessment" class="mt-1 text-sm text-slate-500">
                    Salvar rascunho reabre a avaliação para edição.
                </p>
            </div>

            <DefectAssessmentStatusBadge
                v-if="assessment"
                :status="assessment.status"
            />
        </div>

        <PreviousAssessmentCard
            v-if="previousAssessment"
            class="mt-5"
            :assessment="previousAssessment"
            title="Avaliação anterior"
        />

        <div
            v-if="currentAssessmentSummary"
            class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avaliação atual</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ currentAssessmentSummary.inspection.number || '—' }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <DefectConditionBadge :condition="currentAssessmentSummary.condition" />
                    <DefectAssessmentStatusBadge :status="currentAssessmentSummary.status" />
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <div class="text-xs uppercase text-slate-500">Condição</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ currentAssessmentSummary.condition_label }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <div class="text-xs uppercase text-slate-500">Concluída em</div>
                    <div class="mt-1 text-sm font-semibold text-slate-900">{{ currentAssessmentSummary.assessed_at || '—' }}</div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <label class="block lg:col-span-2">
                <span :class="labelClass">Condição</span>
                <select v-model="form.condition" :class="inputClass">
                    <option v-for="condition in conditionOptions" :key="condition.value" :value="condition.value">
                        {{ condition.label }}
                    </option>
                </select>
                <p v-if="form.errors.condition" :class="helpClass">{{ form.errors.condition }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Localização</span>
                <input v-model="form.location_description" :class="inputClass" type="text" maxlength="500" autocomplete="off">
                <p v-if="form.errors.location_description" :class="helpClass">{{ form.errors.location_description }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Justificativa</span>
                <textarea v-model="form.reason" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    Obrigatória quando a condição for “não localizada” ou “não inspecionada”.
                </p>
                <p v-if="form.errors.reason" :class="helpClass">{{ form.errors.reason }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Comentário</span>
                <textarea v-model="form.comment" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p class="mt-1 text-xs text-slate-500">
                    Obrigatório para concluir a avaliação.
                </p>
                <p v-if="form.errors.comment" :class="helpClass">{{ form.errors.comment }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Recomendação</span>
                <textarea v-model="form.recommendation" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.recommendation" :class="helpClass">{{ form.errors.recommendation }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Observações internas</span>
                <textarea v-model="form.internal_notes" :class="inputClass" rows="3" maxlength="10000"></textarea>
                <p v-if="form.errors.internal_notes" :class="helpClass">{{ form.errors.internal_notes }}</p>
            </label>
        </div>

        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <button
                v-if="canSaveDraft"
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                @click="submitDraft"
            >
                {{ isCompleteAssessment ? 'Reabrir rascunho' : 'Salvar rascunho' }}
            </button>
            <button
                v-if="canComplete"
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                @click="submitComplete"
            >
                Concluir avaliação
            </button>
        </div>
    </section>
</template>
