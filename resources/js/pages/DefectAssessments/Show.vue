<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectAssessmentStatusBadge from '@/components/domain/defects/DefectAssessmentStatusBadge.vue';
import PhotoGallery from '@/components/domain/view-first/PhotoGallery.vue';
import AssessmentPhotoUpload from '@/components/domain/defects/AssessmentPhotoUpload.vue';
import AssessmentHistoryModal from '@/components/domain/defects/AssessmentHistoryModal.vue';
import { calculateCivilVolume, formatCivilMeasurement } from '@/lib/civilQuantity';

const props = defineProps({
    assessment: { type: Object, required: true },
    quantity: { type: Object, default: null },
    evidence: { type: Array, default: () => [] },
    measurement_units: { type: Array, default: () => [] },
    capabilities: { type: Object, default: () => ({}) },
    gut_classification_ranges: { type: Array, default: () => [] },
    gut_options: { type: Object, default: () => ({ gravity: [], urgency: [], trend: [] }) },
    gut_snapshot: { type: Object, default: null },
    classification: { type: Object, default: null },
    condition_options: { type: Array, default: () => [] },
    origin_type: { type: String, default: 'new' },
    previous_assessment_summary: { type: Object, default: null },
    assessment_history: { type: Array, default: () => [] },
    reinspection_action: { type: Object, default: null },
});

const form = useForm({
    status: props.assessment.status,
    condition: props.assessment.condition,
    location_description: props.assessment.location_description ?? '',
    comment: props.assessment.comment ?? '',
    recommendation: props.assessment.recommendation ?? '',
    reason: props.assessment.reason ?? '',
    internal_notes: props.assessment.internal_notes ?? '',
    item_description: props.assessment.item_description ?? '',
    project_reference: props.assessment.project_reference ?? '',
    impacts_activity: props.assessment.impacts_activity ?? null,
    gravity: props.assessment.gravity ?? null,
    urgency: props.assessment.urgency ?? null,
    trend: props.assessment.trend ?? null,
});

const gutForm = useForm({
    condition: props.assessment.condition,
    gravity: props.assessment.gravity ?? null,
    urgency: props.assessment.urgency ?? null,
    trend: props.assessment.trend ?? null,
});

const editing = reactive({ condition: false, quantity: false, gut: false, narrative: false });
const historyOpen = ref(false);
const startingReinspection = ref(false);

const quantityForm = useForm({
    measurement_value: props.quantity?.measurement_value ?? '',
    measurement_unit: props.quantity?.measurement_unit ?? props.measurement_units[0]?.value ?? 'unit',
    length: props.quantity?.length ?? '',
    height: props.quantity?.height ?? '',
    width: props.quantity?.width ?? '',
    quantity: props.quantity?.quantity ?? 1,
});

const title = computed(() => props.assessment.defect?.code ?? 'Avaliação da avaria');
const subtitle = computed(() => `${props.assessment.defect?.equipment?.tag ?? ''} — ${props.assessment.defect?.title ?? ''}`);
const isPublished = computed(() => props.assessment.status === 'complete');
const requiresEvidence = computed(() => !['not_located', 'not_inspected'].includes(form.condition));
const requiresGut = computed(() => !['repaired', 'not_located', 'not_inspected'].includes(form.condition));
const requiresReason = computed(() => ['not_located', 'not_inspected'].includes(form.condition));
const isInherited = computed(() => props.origin_type === 'inherited');
const keepPublished = computed(() => Boolean(props.capabilities.keep_published));
const canMoveToDraft = computed(() => props.capabilities.can_move_to_draft !== false);
const workflowErrors = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);
const gutCriteria = [
    { key: 'gravity', label: 'Gravidade (G)' },
    { key: 'urgency', label: 'Urgência (U)' },
    { key: 'trend', label: 'Tendência (T)' },
];
const gutConfigured = computed(() => gutCriteria.every((criterion) => props.gut_options[criterion.key]?.length));
const gutReady = computed(() => gutConfigured.value && gutCriteria.every((criterion) => gutForm[criterion.key] !== null && gutForm[criterion.key] !== ''));
const classificationDisplay = computed(() => props.classification ?? {
    code: props.assessment.classification_code,
    label: props.assessment.gut_score === null ? 'Não classificada' : 'Sem classificação para este resultado GUT',
    color: null,
});
const quantityUnitLabel = computed(() => props.measurement_units.find((unit) => unit.value === props.quantity?.measurement_unit)?.label ?? props.quantity?.measurement_unit ?? '');
const isCivil = computed(() => props.assessment.defect?.category === 'CV');
const civilVolume = computed(() => calculateCivilVolume(quantityForm));
const civilFields = [
    { key: 'length', label: 'Comprimento (m)' },
    { key: 'height', label: 'Altura (m)' },
    { key: 'width', label: 'Largura (m)' },
    { key: 'quantity', label: 'Quantidade' },
];
const gutDisplay = computed(() => gutCriteria.map((criterion) => {
    const snapshot = props.gut_snapshot?.criteria?.[criterion.key] ?? null;
    const score = props.assessment[criterion.key] ?? snapshot?.score ?? null;
    const option = props.gut_options[criterion.key]?.find((item) => Number(item.score) === Number(score));

    return { ...criterion, score, color: snapshot?.color ?? option?.color ?? null };
}));
const gutScorePreview = computed(() => gutReady.value
    ? Number(gutForm.gravity) * Number(gutForm.urgency) * Number(gutForm.trend)
    : null);
const previewClassification = computed(() => gutScorePreview.value === null
    ? null
    : props.gut_classification_ranges.find((classification) => Number(classification.lower_limit) <= gutScorePreview.value
        && Number(classification.upper_limit) >= gutScorePreview.value) ?? null);

const inputClass = 'mt-1.5 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100';
const labelClass = 'text-sm font-semibold text-slate-700';
const errorClass = 'mt-1.5 text-xs font-medium text-rose-600';

function changeStatus(status) {
    if (!props.capabilities.status_url
        || status === props.assessment.status
        || (status === 'draft' && !canMoveToDraft.value)) {
        return;
    }

    form.status = status;
    form.patch(props.capabilities.status_url, { preserveScroll: true });
}

function saveNarrative() {
    const action = keepPublished.value
        ? props.capabilities.status_url
        : props.capabilities.update_url;

    if (!action) return;

    form.status = keepPublished.value ? 'complete' : 'draft';
    form.patch(action, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'capabilities', 'flash'],
        onSuccess: () => { editing.narrative = false; },
    });
}

function saveCondition() {
    if (!props.capabilities.update_url || !isInherited.value || isPublished.value) return;

    form.status = 'draft';
    form.patch(props.capabilities.update_url, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'gut_snapshot', 'quantity', 'capabilities', 'flash'],
        onSuccess: () => { editing.condition = false; },
    });
}

function startReinspectionAssessment() {
    if (!props.reinspection_action?.assessment_store_url || startingReinspection.value) return;

    startingReinspection.value = true;
    router.post(props.reinspection_action.assessment_store_url, {
        condition: 'unchanged',
        assessment_action: 'draft',
    }, {
        onFinish: () => { startingReinspection.value = false; },
    });
}

function selectGut(criterion, score) {
    if (props.capabilities.gut_url) gutForm[criterion] = score;
}

function isGutSelected(criterion, score) {
    return gutForm[criterion] !== null
        && gutForm[criterion] !== ''
        && Number(gutForm[criterion]) === Number(score);
}

function saveGut() {
    if (!props.capabilities.gut_url || !gutReady.value) return;
    gutForm.put(props.capabilities.gut_url, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'gut_snapshot', 'gut_options', 'gut_classification_ranges', 'capabilities', 'flash'],
        onSuccess: () => { editing.gut = false; },
    });
}

function saveQuantity() {
    if (!props.capabilities.quantity_url) return;

    quantityForm
        .transform((data) => ({
            quantity: isCivil.value
                ? { length: data.length, height: data.height, width: data.width, quantity: data.quantity }
                : data.measurement_value === '' || data.measurement_value === null
                    ? null
                    : { measurement_value: data.measurement_value, measurement_unit: data.measurement_unit },
        }))
        .put(props.capabilities.quantity_url, {
            preserveScroll: true,
            only: ['assessment', 'quantity', 'capabilities', 'flash'],
            onSuccess: () => { editing.quantity = false; },
        });
}

function removeQuantity() {
    if (!props.capabilities.quantity_url) return;

    quantityForm.measurement_value = '';
    quantityForm
        .transform(() => ({ quantity: null }))
        .put(props.capabilities.quantity_url, {
            preserveScroll: true,
            only: ['assessment', 'quantity', 'capabilities', 'flash'],
            onSuccess: () => { editing.quantity = false; },
        });
}

function startEditing(card) {
    if (card === 'quantity') {
        quantityForm.defaults({
            measurement_value: props.quantity?.measurement_value ?? '',
            measurement_unit: props.quantity?.measurement_unit ?? props.measurement_units[0]?.value ?? 'unit',
            length: props.quantity?.length ?? '',
            height: props.quantity?.height ?? '',
            width: props.quantity?.width ?? '',
            quantity: props.quantity?.quantity ?? 1,
        });
        quantityForm.reset();
    }
    if (card === 'gut') {
        gutForm.defaults({ condition: props.assessment.condition, gravity: props.assessment.gravity ?? null, urgency: props.assessment.urgency ?? null, trend: props.assessment.trend ?? null });
        gutForm.reset();
    }
    if (card === 'narrative') {
        form.defaults({ status: props.assessment.status, condition: props.assessment.condition, location_description: props.assessment.location_description ?? '', comment: props.assessment.comment ?? '', recommendation: props.assessment.recommendation ?? '', reason: props.assessment.reason ?? '', internal_notes: props.assessment.internal_notes ?? '', item_description: props.assessment.item_description ?? '', project_reference: props.assessment.project_reference ?? '', impacts_activity: props.assessment.impacts_activity ?? null, gravity: props.assessment.gravity ?? null, urgency: props.assessment.urgency ?? null, trend: props.assessment.trend ?? null });
        form.reset();
    }
    if (card === 'condition') {
        form.defaults({ ...form.data(), condition: props.assessment.condition, reason: props.assessment.reason ?? '' });
        form.reset('condition', 'reason');
    }
    editing[card] = true;
}

function cancelEditing(card) {
    editing[card] = false;
    if (card === 'quantity') { quantityForm.reset(); quantityForm.clearErrors(); }
    if (card === 'gut') { gutForm.reset(); gutForm.clearErrors(); }
    if (card === 'narrative') { form.reset(); form.clearErrors(); }
    if (card === 'condition') { form.reset('condition', 'reason'); form.clearErrors('condition', 'reason'); }
}
</script>

<template>
    <AppLayout :title="title" :subtitle="subtitle" wide>
        <template #actions>
            <div v-if="capabilities.status_url" class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Status da avaliação">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="!isPublished ? 'bg-amber-100 text-amber-800 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    :disabled="form.processing || (isPublished && !canMoveToDraft)"
                    :title="isPublished && !canMoveToDraft ? 'Remova as marcações antes de mover para rascunho.' : undefined"
                    :aria-pressed="!isPublished"
                    @click="changeStatus('draft')"
                >
                    Rascunho
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="isPublished ? 'bg-emerald-100 text-emerald-800 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                    :disabled="form.processing"
                    :aria-pressed="isPublished"
                    @click="changeStatus('complete')"
                >
                    Publicada
                </button>
            </div>
            <DefectAssessmentStatusBadge v-else :status="assessment.status" />
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div v-if="workflowErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <p class="font-semibold">Não foi possível concluir a ação:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li v-for="message in workflowErrors" :key="message">{{ message }}</li>
                </ul>
            </div>

            <section v-if="reinspection_action" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">Avaliação histórica — somente leitura</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">
                            Continue pela reinspeção {{ reinspection_action.inspection.number }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Este registro pertence a {{ assessment.inspection.number }}. As alterações da avaria devem ser registradas em uma nova avaliação da reinspeção atual.
                        </p>
                    </div>
                    <Link
                        v-if="reinspection_action.assessment_url"
                        :href="reinspection_action.assessment_url"
                        class="shrink-0 rounded-xl bg-amber-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-800"
                    >
                        Abrir avaliação atual
                    </Link>
                    <button
                        v-else-if="reinspection_action.assessment_store_url"
                        type="button"
                        :disabled="startingReinspection"
                        class="shrink-0 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-800 disabled:cursor-wait disabled:opacity-60"
                        @click="startReinspectionAssessment"
                    >
                        {{ startingReinspection ? 'Abrindo…' : 'Avaliar nesta reinspeção' }}
                    </button>
                    <Link
                        v-else
                        :href="reinspection_action.defects_url"
                        class="shrink-0 rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-amber-800 hover:bg-amber-100"
                    >
                        Ir para as avarias
                    </Link>
                </div>
            </section>

            <section v-if="isInherited && previous_assessment_summary" class="rounded-3xl border border-teal-200 bg-teal-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Avaria herdada</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-950">
                            Última avaliação · {{ previous_assessment_summary.inspection.number || '—' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ previous_assessment_summary.condition_label }} ·
                            {{ previous_assessment_summary.classification?.code || 'Sem classificação' }} ·
                            {{ previous_assessment_summary.assessed_at || 'Data não informada' }}
                            <span v-if="previous_assessment_summary.quantity">
                                · {{ previous_assessment_summary.quantity.value }} {{ previous_assessment_summary.quantity.unit_symbol }}
                            </span>
                        </p>
                    </div>
                    <button type="button" class="shrink-0 rounded-xl border border-teal-300 bg-white px-4 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-100" @click="historyOpen = true">
                        Ver histórico
                    </button>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">01 · Situação atual</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Evolução da avaria nesta inspeção</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            <template v-if="isInherited">Confirme como a avaria foi encontrada neste ciclo.</template>
                            <template v-else>Esta é a primeira avaliação da avaria.</template>
                        </p>
                    </div>
                    <button v-if="isInherited && capabilities.update_url && !isPublished && !editing.condition" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('condition')">Editar</button>
                </div>

                <div v-if="editing.condition" class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    <label class="block">
                        <span :class="labelClass">Situação</span>
                        <select v-model="form.condition" :class="inputClass">
                            <option v-for="option in condition_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <p v-if="form.errors.condition" :class="errorClass">{{ form.errors.condition }}</p>
                    </label>
                    <label v-if="requiresReason" class="block">
                        <span :class="labelClass">Justificativa</span>
                        <textarea v-model="form.reason" rows="4" maxlength="10000" :class="inputClass"></textarea>
                        <p class="mt-1.5 text-xs text-slate-500">Obrigatória para publicar esta situação.</p>
                        <p v-if="form.errors.reason" :class="errorClass">{{ form.errors.reason }}</p>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('condition')">Cancelar</button>
                        <button type="button" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveCondition">Salvar situação</button>
                    </div>
                </div>
                <div v-else class="mt-5 border-t border-slate-100 pt-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-bold text-white">{{ assessment.condition_label }}</span>
                        <span v-if="requiresReason && assessment.reason" class="text-sm text-slate-600">{{ assessment.reason }}</span>
                    </div>
                    <p v-if="isPublished" class="mt-3 text-xs text-slate-500">Mova a avaliação para rascunho antes de alterar a situação.</p>
                </div>
            </section>

            <section v-if="requiresEvidence" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">02 · Quantitativo</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">{{ isCivil ? 'Volume da avaria' : 'Dimensão principal da manifestação' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ isCivil ? 'Informe as dimensões em metros e a quantidade. O volume será calculado automaticamente.' : 'Valor total observado na avaria.' }}<span v-if="requiresEvidence"> Obrigatório para publicar.</span>
                        </p>
                    </div>
                    <button v-if="capabilities.quantity_url && !editing.quantity" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('quantity')">Editar</button>
                </div>
                <div v-if="editing.quantity" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                    <template v-if="isCivil">
                        <label v-for="field in civilFields" :key="field.key" class="block">
                            <span :class="labelClass">{{ field.label }}</span>
                            <input v-model.number="quantityForm[field.key]" :disabled="!capabilities.quantity_url" type="number" min="0.0001" step="0.0001" :class="inputClass">
                            <p v-if="quantityForm.errors[`quantity.${field.key}`]" :class="errorClass">{{ quantityForm.errors[`quantity.${field.key}`] }}</p>
                        </label>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p :class="labelClass">M³ UNI.</p>
                            <p class="mt-1 text-base font-semibold text-slate-900" aria-live="polite">{{ formatCivilMeasurement(civilVolume?.unitVolume) }} m³</p>
                            <p class="mt-1 text-xs text-slate-500">Comprimento × altura × largura</p>
                        </div>
                        <div class="rounded-xl bg-teal-50 p-4">
                            <p :class="labelClass">M³ TOTAL · Quantitativo</p>
                            <p class="mt-1 text-base font-semibold text-teal-900" aria-live="polite">{{ formatCivilMeasurement(civilVolume?.totalVolume) }} m³</p>
                            <p class="mt-1 text-xs text-slate-500">M³ UNI. × quantidade</p>
                        </div>
                    </template>
                    <template v-else>
                        <label class="block">
                            <span :class="labelClass">Valor</span>
                            <input v-model.number="quantityForm.measurement_value" :disabled="!capabilities.quantity_url" type="number" min="0.0001" step="0.0001" placeholder="Ex.: 1,20" :class="inputClass">
                            <p v-if="quantityForm.errors['quantity.measurement_value']" :class="errorClass">{{ quantityForm.errors['quantity.measurement_value'] }}</p>
                        </label>
                        <label class="block">
                            <span :class="labelClass">Unidade</span>
                            <select v-model="quantityForm.measurement_unit" :disabled="!capabilities.quantity_url" :class="inputClass">
                                <option v-for="unitOption in measurement_units" :key="unitOption.value" :value="unitOption.value">{{ unitOption.label }}</option>
                            </select>
                            <p v-if="quantityForm.errors['quantity.measurement_unit']" :class="errorClass">{{ quantityForm.errors['quantity.measurement_unit'] }}</p>
                        </label>
                    </template>
                </div>
                <p v-if="editing.quantity && quantityForm.errors.quantity" :class="errorClass">{{ quantityForm.errors.quantity }}</p>
                <div v-if="editing.quantity && capabilities.quantity_url" class="mt-4 flex flex-wrap justify-end gap-3">
                    <button v-if="quantity" type="button" :disabled="quantityForm.processing" class="rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 disabled:opacity-50" @click="removeQuantity">Remover quantitativo</button>
                    <button type="button" :disabled="quantityForm.processing" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('quantity')">Cancelar</button>
                    <button type="button" :disabled="quantityForm.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveQuantity">Salvar quantitativo</button>
                </div>
                <div v-else class="mt-5 border-t border-slate-100 pt-5">
                    <div v-if="quantity && isCivil" class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in civilFields" :key="field.key">
                            <p class="text-sm text-slate-500">{{ field.label }}</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatCivilMeasurement(quantity[field.key]) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">M³ UNI.</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatCivilMeasurement(quantity.unit_volume) }} m³</p>
                        </div>
                        <div>
                            <p class="text-sm text-teal-700">M³ TOTAL · Quantitativo</p>
                            <p class="mt-1 text-base font-semibold text-teal-900">{{ formatCivilMeasurement(quantity.measurement_value) }} m³</p>
                        </div>
                    </div>
                    <p v-else-if="quantity" class="text-base font-semibold text-slate-900">{{ quantity.measurement_value }} {{ quantityUnitLabel }}</p>
                    <p v-else class="text-sm text-slate-500">Nenhum quantitativo informado.</p>
                </div>
            </section>

            <section v-if="requiresGut" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-600">03 · Classificação GUT</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Notas desta categoria</h2>
                        <p class="mt-1 text-sm text-slate-500">O produto G×U×T define automaticamente a classificação da categoria.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="gut_snapshot" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">Snapshot salvo</span>
                        <button v-if="capabilities.gut_url && !editing.gut" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('gut')">Editar</button>
                    </div>
                </div>
                <p v-if="!gutConfigured" class="mt-5 border-t border-slate-100 pt-5 text-sm text-amber-700">As notas GUT não estão disponíveis. Atualize a página para tentar novamente.</p>
                <div v-else-if="editing.gut" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-3">
                    <div v-for="criterion in gutCriteria" :key="criterion.key" class="rounded-2xl border border-slate-200 p-4">
                        <h3 class="text-sm font-semibold text-slate-800">{{ criterion.label }}</h3>
                        <div class="mt-3 flex flex-wrap gap-2" role="group" :aria-label="criterion.label">
                            <button v-for="option in gut_options[criterion.key]" :key="option.score" type="button" :disabled="gutForm.processing" class="min-w-11 rounded-lg border px-3 py-2 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-slate-400 disabled:cursor-not-allowed disabled:opacity-70" :class="isGutSelected(criterion.key, option.score) ? 'ring-2 ring-slate-800 ring-offset-1' : ''" :style="{ borderColor: option.color, backgroundColor: `${option.color}22` }" :aria-pressed="isGutSelected(criterion.key, option.score)" @click="selectGut(criterion.key, option.score)">{{ option.score }}</button>
                        </div>
                        <p v-if="gutForm.errors[criterion.key]" :class="errorClass">{{ gutForm.errors[criterion.key] }}</p>
                    </div>
                </div>
                <div v-if="editing.gut" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado automático</p>
                    <p v-if="gutScorePreview !== null" class="mt-1 font-semibold text-slate-900">
                        {{ gutScorePreview }} · {{ previewClassification ? `${previewClassification.code} — ${previewClassification.name}` : 'Sem classificação para este resultado GUT' }}
                    </p>
                    <p v-else class="mt-1 text-slate-600">Selecione G, U e T para calcular o resultado.</p>
                    <p v-if="gutForm.errors.gut" :class="errorClass">{{ gutForm.errors.gut }}</p>
                </div>
                <div v-if="editing.gut" class="mt-5 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('gut')">Cancelar</button>
                    <button type="button" :disabled="gutForm.processing || !gutReady" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveGut">Salvar GUT</button>
                </div>
                <div v-else-if="gutConfigured" class="mt-5 grid gap-3 border-t border-slate-100 pt-5 md:grid-cols-3">
                    <div v-for="item in gutDisplay" :key="item.key" class="rounded-2xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500">{{ item.label }}</p>
                        <span v-if="item.score !== null" class="mt-3 inline-flex min-w-10 items-center justify-center rounded-lg px-3 py-2 text-base font-bold" :style="item.color ? { backgroundColor: item.color, color: '#111827' } : {}" :class="item.color ? '' : 'bg-slate-100 text-slate-700'">{{ item.score }}</span>
                        <p v-else class="mt-3 text-sm text-slate-500">Não definida</p>
                    </div>
                </div>
                <div v-if="gutConfigured && !editing.gut" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado e classificação automáticos</p>
                    <div v-if="assessment.gut_score !== null" class="mt-1 flex flex-wrap items-center gap-3">
                        <span class="font-semibold text-slate-900">{{ assessment.gut_score }}</span>
                        <span class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold" :style="classificationDisplay.color ? { backgroundColor: classificationDisplay.color, color: '#fff' } : {}" :class="classificationDisplay.color ? '' : 'bg-slate-100 text-slate-800'">{{ classificationDisplay.code || '—' }}</span>
                        <span class="text-slate-700">{{ classificationDisplay.label || 'Não classificada' }}</span>
                    </div>
                    <p v-else class="mt-1 text-slate-600">Ainda não calculado.</p>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">04 · Comentário e recomendação</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Registro técnico</h2>
                    </div>
                    <button v-if="capabilities.update_url && !editing.narrative" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('narrative')">Editar</button>
                </div>
                <div v-if="editing.narrative" class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    <label class="block">
                        <span :class="labelClass">Comentário técnico</span>
                        <textarea v-model="form.comment" rows="5" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                        <p class="mt-1.5 text-xs text-slate-500">Obrigatório para publicar a avaliação.</p>
                        <p v-if="form.errors.comment" :class="errorClass">{{ form.errors.comment }}</p>
                    </label>
                    <label class="block">
                        <span :class="labelClass">Recomendação</span>
                        <textarea v-model="form.recommendation" rows="5" maxlength="10000" :class="inputClass" :disabled="!capabilities.update"></textarea>
                        <p v-if="form.errors.recommendation" :class="errorClass">{{ form.errors.recommendation }}</p>
                    </label>
                </div>
                <div v-if="editing.narrative && capabilities.update_url" class="mt-4 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('narrative')">Cancelar</button>
                    <button type="button" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveNarrative">Salvar comentário e recomendação</button>
                </div>
                <div v-else class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Comentário técnico</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ assessment.comment || 'Nenhum comentário informado.' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recomendação</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ assessment.recommendation || 'Nenhuma recomendação informada.' }}</p>
                    </div>
                </div>
            </section>

            <section v-if="requiresEvidence" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">05 · Registros fotográficos</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Documentação da avaria</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ evidence.length }} item(ns)</span>
                    </div>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    Todas as fotografias prontas serão incluídas no documento conforme a ordem definida abaixo.
                    <span v-if="requiresEvidence">Para publicar, anexe pelo menos duas e aguarde o processamento de todas.</span>
                </p>
                <AssessmentPhotoUpload v-if="capabilities.photo_upload_url" class="mt-5" :action="capabilities.photo_upload_url" />
                <div class="mt-5">
                    <PhotoGallery :photos="evidence" :editable="capabilities.update" empty-message="Nenhuma fotografia anexada." />
                </div>
            </section>
        </div>

        <AssessmentHistoryModal :open="historyOpen" :history="assessment_history" :defect-code="assessment.defect?.code" @close="historyOpen = false" />
    </AppLayout>
</template>
