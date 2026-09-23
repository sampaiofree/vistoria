<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import DefectAssessmentStatusBadge from '@/components/domain/defects/DefectAssessmentStatusBadge.vue';
import GutScoreSelect from '@/components/domain/defects/GutScoreSelect.vue';
import PhotoGallery from '@/components/domain/view-first/PhotoGallery.vue';
import AssessmentPhotoUpload from '@/components/domain/defects/AssessmentPhotoUpload.vue';
import AssessmentHistoryModal from '@/components/domain/defects/AssessmentHistoryModal.vue';
import AssessmentLocationModal from '@/components/domain/inspection-locations/AssessmentLocationModal.vue';
import InspectionLocationReportMap from '@/components/domain/inspection-locations/InspectionLocationReportMap.vue';
import CorrectionRequestsPanel from '@/components/domain/inspections/CorrectionRequestsPanel.vue';
import {
    buildNativeQuantityPayload,
    calculateNativeQuantity,
    formatNativeMeasurement,
    nativeUnitSymbol,
} from '@/lib/nativeQuantity';
import {
    buildTechnicalGutPayload,
    calculateTechnicalGut,
    technicalGutReady,
    transporterTypesFor,
    trendOptionsFor,
    urgencyContextsFor,
    urgencyMatricesFor,
    urgencyOptionsFor,
} from '@/lib/technicalGut';

const props = defineProps({
    assessment: { type: Object, required: true },
    quantities: { type: Array, default: () => [] },
    quantity_summary: { type: Object, default: () => ({}) },
    evidence: { type: Array, default: () => [] },
    measurement_units: { type: Array, default: () => [] },
    capabilities: { type: Object, default: () => ({}) },
    gut_classification_ranges: { type: Array, default: () => [] },
    gut_options: { type: Object, default: () => ({ gravity: [], urgency: [], trend: [] }) },
    gut_definition: { type: Object, default: null },
    gut_snapshot: { type: Object, default: null },
    tel_definition: { type: Object, default: null },
    tel_snapshot: { type: Object, default: null },
    tel_classification_ranges: { type: Array, default: () => [] },
    quantity_definition: { type: Object, default: null },
    classification: { type: Object, default: null },
    condition_options: { type: Array, default: () => [] },
    origin_type: { type: String, default: 'new' },
    previous_assessment_summary: { type: Object, default: null },
    assessment_history: { type: Array, default: () => [] },
    reinspection_action: { type: Object, default: null },
    location_map: { type: Object, default: null },
    correction_requests: { type: Object, default: () => ({ history: [] }) },
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
});

function snapshotCriterion(criterion) {
    return props.gut_snapshot?.criteria?.[criterion] ?? {};
}

function gutDefaults() {
    const gravity = snapshotCriterion('gravity');
    const urgency = snapshotCriterion('urgency');
    const trend = snapshotCriterion('trend');

    return {
        condition: props.assessment.condition,
        safety_impact_code: gravity.safety_impact?.code ?? '',
        asset_impact_code: gravity.asset_impact?.code ?? '',
        urgency_context_code: urgency.context?.code ?? '',
        urgency_matrix_code: urgency.matrix?.code ?? '',
        transporter_type_code: urgency.transporter_type?.code ?? '',
        urgency_option_code: urgency.option?.code ?? '',
        trend_group_code: trend.group?.code ?? '',
        trend_option_code: trend.option?.code ?? '',
    };
}

const gutForm = useForm(gutDefaults());
const telForm = useForm({
    condition: props.assessment.condition,
    height_m: props.tel_snapshot?.height_m ?? '',
    damage_group_code: props.tel_snapshot?.damage_group?.code ?? '',
    damage_option_code: props.tel_snapshot?.damage_option?.code ?? '',
});

const editing = reactive({ condition: false, quantity: false, gut: false, tel: false, narrative: false });
const historyOpen = ref(false);
const locationModalOpen = ref(false);
const startingReinspection = ref(false);
const mapForm = useForm({ file: null });
const mapPreviewUrl = ref(null);
let mapProcessingPoll = null;

const locationEditorMap = computed(() => props.location_map?.editor
    ? {
        ...props.location_map.editor,
        location: props.location_map.location,
        color: props.location_map.color,
        photo_legend: props.location_map.photo_legend,
    }
    : null);

const locationPreviewMap = computed(() => {
    const locationMap = props.location_map;
    const location = locationMap?.location;

    if (!locationMap?.background_url || !location?.confirmed) return null;

    return {
        title: `Localização da avaria ${props.assessment.defect?.code ?? ''}`.trim(),
        background: {
            url: locationMap.background_url,
            width: locationMap.background_width,
            height: locationMap.background_height,
        },
        markers: [{
            public_id: location.public_id,
            geometry: location.geometry,
            style: locationMap.style,
            label: location.label,
            photo_legend: locationMap.photo_legend,
        }],
    };
});

const quantityForm = useForm({
    description: '',
    element: '',
    area: '',
    total_weight: '',
    length: '',
    height: '',
    width: '',
    quantity: 1,
});
const editingQuantity = ref(null);

const title = computed(() => props.assessment.defect?.code ?? 'Avaliação da avaria');
const subtitle = computed(() => `${props.assessment.defect?.equipment?.tag ?? ''} — ${props.assessment.defect?.title ?? ''}`);
const isPublished = computed(() => props.assessment.status === 'complete');
const requiresEvidence = computed(() => !['canceled', 'canceled_sr'].includes(form.condition));
const requiresGut = computed(() => ['new', 'reinspected', 'reclassified'].includes(form.condition));
const requiresReason = computed(() => ['canceled', 'canceled_sr'].includes(form.condition));
const isInherited = computed(() => props.origin_type === 'inherited');
const keepPublished = computed(() => Boolean(props.capabilities.keep_published));
const canMoveToDraft = computed(() => props.capabilities.can_move_to_draft !== false);
const workflowErrors = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);
const gutCriteria = [
    { key: 'gravity', label: 'Gravidade (G)' },
    { key: 'urgency', label: 'Urgência (U)' },
    { key: 'trend', label: 'Tendência (T)' },
];
const defectCategory = computed(() => props.gut_definition?.category?.code
    ?? props.gut_definition?.category
    ?? props.quantity_definition?.category?.code
    ?? props.quantity_definition?.category
    ?? props.assessment.defect?.category);
const hasTechnicalGut = computed(() => Boolean(props.gut_definition?.category));
const gutConfigured = computed(() => hasTechnicalGut.value);
const technicalGutPreview = computed(() => hasTechnicalGut.value ? calculateTechnicalGut(props.gut_definition, gutForm) : null);
const gutReady = computed(() => hasTechnicalGut.value && technicalGutReady(props.gut_definition, gutForm));
const classificationDisplay = computed(() => props.classification ?? {
    code: props.assessment.classification_code,
    label: props.assessment.gut_score === null ? 'Não classificada' : 'Sem classificação para este resultado GUT',
    color: null,
});
const quantityUnitLabel = computed(() => nativeUnitSymbol(props.quantity_summary?.unit_value)
    || props.quantity_summary?.unit
    || '');
const isCivil = computed(() => defectCategory.value === 'CV');
const isTac = computed(() => defectCategory.value === 'TAC');
const isRec = computed(() => defectCategory.value === 'REC');
const isTel = computed(() => defectCategory.value === 'TEL');
const civilFields = [
    { key: 'length', label: 'Comprimento (m)' },
    { key: 'height', label: 'Altura (m)' },
    { key: 'width', label: 'Largura (m)' },
    { key: 'quantity', label: 'Quantidade' },
];
const recElements = computed(() => props.quantity_definition?.elements ?? []);
const selectedRecElement = computed(() => recElements.value.find((element) => element.code === quantityForm.element) ?? null);
const recQuantityFields = computed(() => {
    const fields = [...(selectedRecElement.value?.fields ?? [])].filter((field) => field.key !== 'quantity' && field.key !== 'total_weight');
    if (selectedRecElement.value?.mode !== 'manual') fields.push({ key: 'quantity', label: 'Quantidade', unit: null });

    return fields;
});
const quantityPreview = computed(() => calculateNativeQuantity(defectCategory.value, quantityForm));
const quantityReady = computed(() => quantityPreview.value !== null);
const trendOptions = computed(() => trendOptionsFor(props.gut_definition, gutForm.trend_group_code));
const civilUrgencyContexts = computed(() => urgencyContextsFor(props.gut_definition));
const civilUrgencyOptions = computed(() => urgencyOptionsFor(
    props.gut_definition,
    gutForm.urgency_context_code,
));
const civilUrgencyPreview = computed(() => civilUrgencyOptions.value
    .find((option) => option.code === gutForm.urgency_option_code) ?? null);
const civilTrendPreview = computed(() => trendOptions.value
    .find((option) => option.code === gutForm.trend_option_code) ?? null);
const recUrgencyMatrices = computed(() => urgencyMatricesFor(props.gut_definition));
const recTransporterTypes = computed(() => transporterTypesFor(
    props.gut_definition,
    gutForm.urgency_matrix_code,
));
const recUrgencyOptions = computed(() => urgencyOptionsFor(
    props.gut_definition,
    gutForm.urgency_matrix_code,
    gutForm.transporter_type_code,
));
const recUrgencyPreview = computed(() => recUrgencyOptions.value
    .find((option) => option.code === gutForm.urgency_option_code) ?? null);
const gutDisplay = computed(() => gutCriteria.map((criterion) => {
    const snapshot = props.gut_snapshot?.criteria?.[criterion.key] ?? null;
    const score = props.assessment[criterion.key] ?? snapshot?.score ?? null;
    const option = props.gut_options[criterion.key]?.find((item) => Number(item.score) === Number(score));

    let details = [];
    if (criterion.key === 'gravity') details = [snapshot?.safety_impact?.label, snapshot?.asset_impact?.label];
    if (criterion.key === 'urgency') details = [snapshot?.context?.label, snapshot?.matrix?.label, snapshot?.transporter_type?.label, snapshot?.option?.label, snapshot?.source?.label];
    if (criterion.key === 'trend') details = [snapshot?.group?.label, snapshot?.option?.label];

    return { ...criterion, score, color: snapshot?.color ?? option?.color ?? null, details: details.filter(Boolean) };
}));
const gutScorePreview = computed(() => technicalGutPreview.value?.score ?? null);
const previewClassification = computed(() => gutScorePreview.value === null
    ? null
    : props.gut_classification_ranges.find((classification) => Number(classification.lower_limit) <= gutScorePreview.value
        && Number(classification.upper_limit) >= gutScorePreview.value) ?? null);
const telDamageOptions = computed(() => (props.tel_definition?.damage_groups ?? [])
    .find((group) => group.code === telForm.damage_group_code)?.options ?? []);
const telPreview = computed(() => {
    const height = Number(telForm.height_m);
    const option = telDamageOptions.value.find((item) => item.code === telForm.damage_option_code);
    if (!Number.isFinite(height) || height < 0 || !option) return null;
    const impact = height <= 10 ? 3 : height <= 15 ? 4 : 5;
    const risk = Number(option.score);
    return { impact, risk, score: impact * risk };
});
const telReady = computed(() => Boolean(telPreview.value && telForm.damage_group_code && telForm.damage_option_code));
const telPreviewClassification = computed(() => telPreview.value === null
    ? null
    : props.tel_classification_ranges.find((classification) => Number(classification.lower_limit) <= telPreview.value.score
        && Number(classification.upper_limit) >= telPreview.value.score) ?? null);

const controlClass = 'block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100';
const inputClass = `mt-1.5 ${controlClass}`;
const unitInputClass = `${controlClass} pr-12`;
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
    if (!props.capabilities.update_url || isPublished.value) return;

    form.status = 'draft';
    form.patch(props.capabilities.update_url, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'gut_snapshot', 'quantities', 'quantity_summary', 'capabilities', 'flash'],
        onSuccess: () => { editing.condition = false; },
    });
}

function startReinspectionAssessment() {
    if (!props.reinspection_action?.assessment_store_url || startingReinspection.value) return;

    startingReinspection.value = true;
    router.post(props.reinspection_action.assessment_store_url, {
        condition: 'reinspected',
        assessment_action: 'draft',
    }, {
        onFinish: () => { startingReinspection.value = false; },
    });
}

function saveGut() {
    if (!props.capabilities.gut_url || !gutReady.value) return;
    gutForm
        .transform((data) => buildTechnicalGutPayload(props.gut_definition, data))
        .put(props.capabilities.gut_url, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'gut_definition', 'gut_snapshot', 'gut_options', 'gut_classification_ranges', 'capabilities', 'flash'],
        onSuccess: () => { editing.gut = false; },
    });
}

function saveTel() {
    if (!props.capabilities.tel_url || !telReady.value) return;
    telForm.put(props.capabilities.tel_url, {
        preserveScroll: true,
        only: ['assessment', 'classification', 'tel_snapshot', 'tel_definition', 'tel_classification_ranges', 'capabilities', 'flash'],
        onSuccess: () => { editing.tel = false; },
    });
}

function saveQuantity() {
    const action = editingQuantity.value?.update_url ?? props.capabilities.quantity_store_url;
    if (!action) return;

    quantityForm
        .transform((data) => ({
            description: data.description || null,
            quantity: buildNativeQuantityPayload(defectCategory.value, data, props.quantity_definition),
        }))
        .submit(editingQuantity.value ? 'put' : 'post', action, {
            preserveScroll: true,
            only: ['assessment', 'quantities', 'quantity_summary', 'capabilities', 'flash'],
            onSuccess: () => cancelEditing('quantity'),
        });
}

function removeQuantity(item) {
    if (!item.delete_url) return;
    router.delete(item.delete_url, {
        preserveScroll: true,
        only: ['assessment', 'quantities', 'quantity_summary', 'capabilities', 'flash'],
        onSuccess: () => {
            if (editingQuantity.value?.public_id === item.public_id) cancelEditing('quantity');
        },
    });
}

function quantityDefaults(item = null) {
    const inputs = item?.inputs ?? {};
    return {
        description: item?.description ?? '',
        ...inputs,
        element: item?.rec_element ?? item?.element_code ?? '',
        area: inputs.area ?? (isTac.value ? item?.measurement_value ?? '' : ''),
        total_weight: inputs.total_weight ?? (item?.mode === 'manual' ? item?.measurement_value ?? '' : ''),
        length: inputs.length ?? '',
        height: inputs.height ?? '',
        width: inputs.width ?? '',
        quantity: item?.quantity ?? inputs.quantity ?? 1,
    };
}

function editQuantity(item = null) {
    editingQuantity.value = item;
    quantityForm.defaults(quantityDefaults(item));
    quantityForm.reset();
    quantityForm.clearErrors();
    editing.quantity = true;
}

function startEditing(card) {
    if (card === 'quantity') {
        editQuantity();
        return;
    }
    if (card === 'gut') {
        gutForm.defaults(gutDefaults());
        gutForm.reset();
    }
    if (card === 'tel') {
        telForm.defaults({
            condition: props.assessment.condition,
            height_m: props.tel_snapshot?.height_m ?? '',
            damage_group_code: props.tel_snapshot?.damage_group?.code ?? '',
            damage_option_code: props.tel_snapshot?.damage_option?.code ?? '',
        });
        telForm.reset();
    }
    if (card === 'narrative') {
        form.defaults({ status: props.assessment.status, condition: props.assessment.condition, location_description: props.assessment.location_description ?? '', comment: props.assessment.comment ?? '', recommendation: props.assessment.recommendation ?? '', reason: props.assessment.reason ?? '', internal_notes: props.assessment.internal_notes ?? '', item_description: props.assessment.item_description ?? '', project_reference: props.assessment.project_reference ?? '', impacts_activity: props.assessment.impacts_activity ?? null });
        form.reset();
    }
    if (card === 'condition') {
        form.defaults({ ...form.data(), condition: props.assessment.condition, reason: props.assessment.reason ?? '' });
        form.reset('condition', 'reason');
    }
    editing[card] = true;
}

function changeTrendGroup() {
    gutForm.trend_option_code = '';
}

function changeCivilUrgencyContext() {
    gutForm.urgency_option_code = '';
}

function changeRecUrgencyMatrix() {
    gutForm.transporter_type_code = '';
    gutForm.urgency_option_code = '';
}

function changeRecTransporterType() {
    gutForm.urgency_option_code = '';
}

function changeTelDamageGroup() {
    telForm.damage_option_code = '';
}

function changeQuantityElement() {
    for (const element of recElements.value) {
        for (const field of element.fields ?? []) quantityForm[field.key] = '';
    }
    quantityForm.quantity = 1;
    quantityForm.total_weight = '';
}

function quantityElementLabel(item) {
    const code = item.rec_element ?? item.element_code;
    return recElements.value.find((element) => element.code === code)?.label ?? code;
}

const quantityInputLabels = {
    length: 'Comprimento', height: 'Altura', width: 'Largura', quantity: 'Quantidade', area: 'Área',
    flange_width: 'Mesa', flange_thickness: 'Espessura da mesa', web_height: 'Alma',
    web_thickness: 'Espessura da alma', thickness: 'Espessura', fold_width: 'Dobra da mesa',
    outer_diameter: 'Diâmetro externo', leg_1: 'Aba 1', leg_2: 'Aba 2', side_1: 'Aba 1',
    side_2: 'Aba 2', total_weight: 'Peso total',
};

function quantityInputLabel(key) {
    return quantityInputLabels[key] ?? key;
}

function civilInputUnit(key) {
    return isCivil.value && ['length', 'height', 'width'].includes(key) ? 'm' : null;
}

function blockInvalidNumberKey(event) {
    if (['e', 'E', '+', '-'].includes(event.key)) event.preventDefault();
}

function cancelEditing(card) {
    editing[card] = false;
    if (card === 'quantity') {
        editingQuantity.value = null;
        quantityForm.defaults(quantityDefaults());
        quantityForm.reset();
        quantityForm.clearErrors();
    }
    if (card === 'gut') { gutForm.reset(); gutForm.clearErrors(); }
    if (card === 'tel') { telForm.reset(); telForm.clearErrors(); }
    if (card === 'narrative') { form.reset(); form.clearErrors(); }
    if (card === 'condition') { form.reset('condition', 'reason'); form.clearErrors('condition', 'reason'); }
}

function clearMapPreview() {
    if (mapPreviewUrl.value) URL.revokeObjectURL(mapPreviewUrl.value);
    mapPreviewUrl.value = null;
}

function selectMapFile(event) {
    clearMapPreview();
    mapForm.file = event.target.files[0] || null;
    mapForm.clearErrors();
    if (mapForm.file) mapPreviewUrl.value = URL.createObjectURL(mapForm.file);
}

function uploadMap() {
    if (!props.capabilities.location_map_upload_url || !mapForm.file) return;
    mapForm.post(props.capabilities.location_map_upload_url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearMapPreview();
            mapForm.reset('file');
        },
    });
}

function removeMap() {
    if (!props.capabilities.location_map_delete_url || !window.confirm('Remover o mapa e a localização desta avaliação?')) return;
    router.delete(props.capabilities.location_map_delete_url, { preserveScroll: true });
}

function pollMapProcessing() {
    if (mapProcessingPoll) window.clearInterval(mapProcessingPoll);
    mapProcessingPoll = null;
    if (!['pending', 'processing'].includes(props.location_map?.processing_status)) return;
    mapProcessingPoll = window.setInterval(() => {
        router.reload({ only: ['location_map', 'assessment', 'capabilities', 'flash'], preserveScroll: true, preserveState: true });
    }, 2500);
}

watch(() => props.location_map?.processing_status, pollMapProcessing);
onMounted(pollMapProcessing);
onUnmounted(() => {
    if (mapProcessingPoll) window.clearInterval(mapProcessingPoll);
    clearMapPreview();
});
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
            <div v-if="isPublished" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900" role="status">
                Este documento só pode ser editado no modo rascunho.
            </div>

            <div v-if="workflowErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <p class="font-semibold">Não foi possível concluir a ação:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">
                    <li v-for="message in workflowErrors" :key="message">{{ message }}</li>
                </ul>
            </div>

            <CorrectionRequestsPanel
                v-if="correction_requests.create_url || correction_requests.items?.length || correction_requests.history?.length"
                :correction="correction_requests"
                title="Solicitações de correção desta avaria"
                empty_label="Nenhuma correção solicitada para esta avaria."
            />

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
                    <button v-if="capabilities.update_url && !isPublished && !editing.condition" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('condition')">Editar</button>
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
                <div v-if="isInherited && previous_assessment_summary" class="mt-4 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-slate-700">
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-800">Última revisão · {{ previous_assessment_summary.inspection.number || '—' }} · {{ previous_assessment_summary.assessed_at || 'Data não informada' }}</p>
                    <p class="mt-2"><strong class="text-slate-900">Situação:</strong> {{ previous_assessment_summary.condition_label }}</p>
                    <p v-if="previous_assessment_summary.reason" class="mt-1 whitespace-pre-line"><strong class="text-slate-900">Justificativa:</strong> {{ previous_assessment_summary.reason }}</p>
                </div>
            </section>

            <section v-if="requiresEvidence && !isTel" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">02 · Quantitativo</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">
                            {{ isCivil ? 'Volume da avaria' : isTac ? 'Área da avaria' : 'Peso do elemento' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            <template v-if="isCivil">Informe as dimensões em metros e a quantidade. O volume será calculado automaticamente.</template>
                            <template v-else-if="isTac">Informe a área total observada em metros quadrados.</template>
                            <template v-else>Escolha o elemento e informe suas dimensões. O peso será calculado quando houver fórmula.</template>
                            <span> Obrigatório para publicar.</span>
                        </p>
                    </div>
                    <button v-if="capabilities.quantity_store_url && !editing.quantity" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="editQuantity()">Adicionar item</button>
                </div>
                <div v-if="editing.quantity" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <p class="text-sm font-semibold text-slate-900">{{ editingQuantity ? `Editar item ${editingQuantity.position}` : 'Novo item' }}</p>
                    </div>
                    <label class="block sm:col-span-2">
                        <span :class="labelClass">Descrição do item <span class="font-normal text-slate-400">(opcional)</span></span>
                        <input v-model="quantityForm.description" maxlength="180" type="text" :class="inputClass" placeholder="Ex.: Trecho junto ao apoio norte">
                        <p v-if="quantityForm.errors.description" :class="errorClass">{{ quantityForm.errors.description }}</p>
                    </label>
                    <template v-if="isCivil">
                        <label v-for="field in civilFields" :key="field.key" class="block">
                            <span :class="labelClass">{{ field.label }}</span>
                            <div class="relative mt-1.5">
                                <input v-model="quantityForm[field.key]" type="number" inputmode="decimal" min="0.0001" step="any" :class="unitInputClass" @keydown="blockInvalidNumberKey">
                                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center border-l border-slate-200 px-3 text-sm font-semibold text-slate-500">{{ field.key === 'quantity' ? 'un.' : 'm' }}</span>
                            </div>
                            <p v-if="quantityForm.errors[`quantity.${field.key}`]" :class="errorClass">{{ quantityForm.errors[`quantity.${field.key}`] }}</p>
                        </label>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p :class="labelClass">M³ UNI.</p>
                            <p class="mt-1 text-base font-semibold text-slate-900" aria-live="polite">{{ formatNativeMeasurement(quantityPreview?.unitValue) }} m³</p>
                            <p class="mt-1 text-xs text-slate-500">Comprimento × altura × largura</p>
                        </div>
                        <div class="rounded-xl bg-teal-50 p-4">
                            <p :class="labelClass">M³ TOTAL · Quantitativo</p>
                            <p class="mt-1 text-base font-semibold text-teal-900" aria-live="polite">{{ formatNativeMeasurement(quantityPreview?.totalValue) }} m³</p>
                            <p class="mt-1 text-xs text-slate-500">M³ UNI. × quantidade</p>
                        </div>
                    </template>
                    <template v-else-if="isTac">
                        <label class="block">
                            <span :class="labelClass">Área (m²)</span>
                            <input v-model="quantityForm.area" inputmode="decimal" type="number" min="0.0001" step="any" placeholder="Ex.: 1,20" :class="inputClass">
                            <p v-if="quantityForm.errors['quantity.area']" :class="errorClass">{{ quantityForm.errors['quantity.area'] }}</p>
                        </label>
                        <div class="rounded-xl bg-teal-50 p-4">
                            <p :class="labelClass">Área total · Quantitativo</p>
                            <p class="mt-1 text-base font-semibold text-teal-900" aria-live="polite">{{ formatNativeMeasurement(quantityPreview?.totalValue) }} m²</p>
                            <p class="mt-1 text-xs text-slate-500">Valor informado manualmente</p>
                        </div>
                    </template>
                    <template v-else-if="isRec">
                        <label class="block sm:col-span-2">
                            <span :class="labelClass">Elemento REC</span>
                            <select v-model="quantityForm.element" :class="inputClass" @change="changeQuantityElement">
                                <option value="">Selecione o elemento</option>
                                <option v-for="element in recElements" :key="element.code" :value="element.code">{{ element.label }}</option>
                            </select>
                            <p v-if="quantityForm.errors['quantity.element']" :class="errorClass">{{ quantityForm.errors['quantity.element'] }}</p>
                        </label>
                        <template v-if="selectedRecElement?.mode === 'manual'">
                            <label class="block">
                                <span :class="labelClass">Peso total (kg)</span>
                                <input v-model="quantityForm.total_weight" type="number" inputmode="decimal" min="0.0001" step="any" :class="inputClass">
                                <p v-if="quantityForm.errors['quantity.total_weight']" :class="errorClass">{{ quantityForm.errors['quantity.total_weight'] }}</p>
                            </label>
                            <div class="rounded-xl bg-amber-50 p-4">
                                <p :class="labelClass">Modo de cálculo</p>
                                <p class="mt-1 font-semibold text-amber-900">Peso informado manualmente</p>
                            </div>
                        </template>
                        <template v-else-if="selectedRecElement">
                            <label v-for="field in recQuantityFields" :key="field.key" class="block">
                                <span :class="labelClass">{{ field.label }}<template v-if="field.unit"> ({{ field.unit }})</template></span>
                                <input v-model="quantityForm[field.key]" type="number" inputmode="decimal" min="0.0001" step="any" :class="inputClass">
                                <p v-if="quantityForm.errors[`quantity.${field.key}`]" :class="errorClass">{{ quantityForm.errors[`quantity.${field.key}`] }}</p>
                            </label>
                            <div class="rounded-xl bg-slate-50 p-4">
                                <p :class="labelClass">Peso unitário</p>
                                <p class="mt-1 text-base font-semibold text-slate-900" aria-live="polite">{{ formatNativeMeasurement(quantityPreview?.unitValue) }} kg</p>
                            </div>
                            <div class="rounded-xl bg-teal-50 p-4">
                                <p :class="labelClass">Peso total · Quantitativo</p>
                                <p class="mt-1 text-base font-semibold text-teal-900" aria-live="polite">{{ formatNativeMeasurement(quantityPreview?.totalValue) }} kg</p>
                            </div>
                        </template>
                    </template>
                </div>
                <div v-if="isInherited && previous_assessment_summary?.quantity" class="mt-5 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-slate-700">
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-800">Última revisão · {{ previous_assessment_summary.inspection.number || '—' }} · {{ previous_assessment_summary.assessed_at || 'Data não informada' }}</p>
                    <p class="mt-2">Os itens abaixo foram copiados da última revisão. Revise-os antes de publicar esta avaliação.</p>
                    <p class="mt-1 font-semibold text-slate-900">Total anterior: {{ formatNativeMeasurement(previous_assessment_summary.quantity.value) }} {{ previous_assessment_summary.quantity.unit_symbol }}</p>
                </div>
                <p v-if="editing.quantity && quantityForm.errors.quantity" :class="errorClass">{{ quantityForm.errors.quantity }}</p>
                <div v-if="editing.quantity && capabilities.quantity_store_url" class="mt-4 flex flex-wrap justify-end gap-3">
                    <button type="button" :disabled="quantityForm.processing" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('quantity')">Cancelar</button>
                    <button type="button" :disabled="quantityForm.processing || !quantityReady" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveQuantity">Salvar item</button>
                </div>
                <div v-else class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                    <article v-for="item in quantities" :key="item.public_id ?? item.position" class="rounded-2xl border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Item {{ item.position }}</p>
                                <p class="mt-1 font-semibold text-slate-950">{{ item.description || (isRec ? quantityElementLabel(item) : 'Sem descrição') }}</p>
                                <p v-if="isRec && item.description" class="mt-1 text-sm text-slate-500">{{ quantityElementLabel(item) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button v-if="item.update_url" type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700" @click="editQuantity(item)">Editar</button>
                                <button v-if="item.delete_url" type="button" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700" @click="removeQuantity(item)">Excluir</button>
                            </div>
                        </div>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="(value, key) in item.inputs" :key="key" class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-xs text-slate-500">{{ quantityInputLabel(key) }}<span v-if="civilInputUnit(key)"> ({{ civilInputUnit(key) }})</span></dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ formatNativeMeasurement(value) }}<span v-if="civilInputUnit(key)"> {{ civilInputUnit(key) }}</span></dd>
                            </div>
                            <div v-if="item.mode !== 'manual' || isCivil" class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-xs text-slate-500">Quantidade</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ formatNativeMeasurement(item.quantity) }}<span v-if="isCivil"> un.</span></dd>
                            </div>
                            <div v-if="item.unit_value" class="rounded-xl bg-slate-50 p-3">
                                <dt class="text-xs text-slate-500">Valor unitário</dt>
                                <dd class="mt-1 font-semibold text-slate-900">{{ formatNativeMeasurement(item.unit_value) }} {{ item.unit }}</dd>
                            </div>
                            <div class="rounded-xl bg-teal-50 p-3">
                                <dt class="text-xs font-medium text-teal-700">Total do item</dt>
                                <dd class="mt-1 font-bold text-teal-900">{{ formatNativeMeasurement(item.measurement_value) }} {{ item.unit }}</dd>
                            </div>
                        </dl>
                    </article>
                    <p v-if="quantities.length === 0" class="text-sm text-slate-500">Nenhum item de quantitativo informado.</p>
                    <div v-else class="rounded-2xl bg-slate-950 p-5 text-white">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-200">Total final · {{ quantities.length }} item(ns)</p>
                        <p class="mt-2 text-2xl font-bold">{{ formatNativeMeasurement(quantity_summary.total_raw ?? quantity_summary.total) }} {{ quantityUnitLabel }}</p>
                    </div>
                </div>
            </section>

            <section v-if="requiresGut && !isTel" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
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
                <div v-if="isInherited && previous_assessment_summary?.gut" class="mt-5 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-slate-700">
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-800">Última revisão · {{ previous_assessment_summary.inspection.number || '—' }} · {{ previous_assessment_summary.assessed_at || 'Data não informada' }}</p>
                    <p class="mt-2"><strong class="text-slate-900">Classificação:</strong> {{ previous_assessment_summary.classification?.code || '—' }} · {{ previous_assessment_summary.classification?.label || 'Sem classificação' }}</p>
                    <p class="mt-1"><strong class="text-slate-900">GUT:</strong> G {{ previous_assessment_summary.gut.gravity ?? '—' }} · U {{ previous_assessment_summary.gut.urgency ?? '—' }} · T {{ previous_assessment_summary.gut.trend ?? '—' }} · {{ previous_assessment_summary.gut.score ?? '—' }}</p>
                </div>
                <p v-if="!gutConfigured" class="mt-5 border-t border-slate-100 pt-5 text-sm text-amber-700">As notas GUT não estão disponíveis. Atualize a página para tentar novamente.</p>
                <div v-else-if="editing.gut && hasTechnicalGut" class="mt-5 space-y-5 border-t border-slate-100 pt-5">
                    <div v-if="isCivil || isRec" class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <span :class="labelClass">Impacto na Segurança</span>
                            <GutScoreSelect
                                v-model="gutForm.safety_impact_code"
                                :options="gut_definition.safety_impact_options"
                                criterion="G"
                                aria-label="Impacto na Segurança"
                                placeholder="Selecione o impacto"
                            />
                            <p v-if="gutForm.errors.safety_impact_code" :class="errorClass">{{ gutForm.errors.safety_impact_code }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <span :class="labelClass">Impacto no Ativo</span>
                            <GutScoreSelect
                                v-model="gutForm.asset_impact_code"
                                :options="gut_definition.asset_impact_options"
                                criterion="G"
                                aria-label="Impacto no Ativo"
                                placeholder="Selecione o impacto"
                            />
                            <p v-if="gutForm.errors.asset_impact_code" :class="errorClass">{{ gutForm.errors.asset_impact_code }}</p>
                        </div>
                    </div>

                    <div v-if="isTac" class="grid gap-4 md:grid-cols-2">
                        <div v-for="criterion in ['gravity', 'urgency']" :key="criterion" class="rounded-2xl border p-4" :class="gut_definition.sources?.[criterion]?.valid ? 'border-slate-200 bg-slate-50' : 'border-amber-300 bg-amber-50'">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ criterion === 'gravity' ? 'Gravidade (G) · Código ABC' : 'Urgência (U) · Atmosfera' }}</p>
                            <p v-if="gut_definition.sources?.[criterion]?.valid" class="mt-2 font-semibold text-slate-900">
                                {{ gut_definition.sources[criterion].label }} · Nota {{ gut_definition.sources[criterion].score }}
                            </p>
                            <p v-else class="mt-2 text-sm font-medium text-amber-800">
                                {{ gut_definition.sources?.[criterion]?.message || 'Corrija este dado no cadastro de origem para calcular o GUT.' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="isCivil" class="rounded-2xl border border-slate-200 p-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label>
                                <span :class="labelClass">Contexto de Urgência</span>
                                <select v-model="gutForm.urgency_context_code" :class="inputClass" @change="changeCivilUrgencyContext">
                                    <option value="">Selecione o contexto</option>
                                    <option v-for="context in civilUrgencyContexts" :key="context.code" :value="context.code">{{ context.label }}</option>
                                </select>
                                <p v-if="gutForm.errors.urgency_context_code" :class="errorClass">{{ gutForm.errors.urgency_context_code }}</p>
                            </label>
                            <div>
                                <span :class="labelClass">Função/elemento</span>
                                <GutScoreSelect
                                    v-model="gutForm.urgency_option_code"
                                    :options="civilUrgencyOptions"
                                    criterion="U"
                                    aria-label="Função ou elemento CIVIL"
                                    placeholder="Selecione a função ou elemento"
                                    :disabled="!gutForm.urgency_context_code"
                                />
                                <p v-if="gutForm.errors.urgency_option_code" :class="errorClass">{{ gutForm.errors.urgency_option_code }}</p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-xl bg-teal-50 p-4">
                            <p :class="labelClass">Urgência (U)</p>
                            <p class="mt-1 text-xl font-bold text-teal-900">{{ civilUrgencyPreview?.score ?? '—' }}</p>
                            <p class="mt-1 text-xs text-slate-600">Calculada automaticamente pelo contexto e elemento selecionados.</p>
                        </div>
                    </div>

                    <div v-else-if="isRec" class="rounded-2xl border border-slate-200 p-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label>
                                <span :class="labelClass">Critério de Urgência</span>
                                <select v-model="gutForm.urgency_matrix_code" :class="inputClass" @change="changeRecUrgencyMatrix">
                                    <option value="">Selecione o critério</option>
                                    <option v-for="matrix in recUrgencyMatrices" :key="matrix.code" :value="matrix.code">{{ matrix.label }}</option>
                                </select>
                                <p v-if="gutForm.errors.urgency_matrix_code" :class="errorClass">{{ gutForm.errors.urgency_matrix_code }}</p>
                            </label>
                            <label v-if="gutForm.urgency_matrix_code === 'patio_port_transporter'">
                                <span :class="labelClass">Tipo do transportador</span>
                                <select v-model="gutForm.transporter_type_code" :class="inputClass" @change="changeRecTransporterType">
                                    <option value="">Selecione o tipo</option>
                                    <option v-for="type in recTransporterTypes" :key="type.code" :value="type.code">{{ type.label }}</option>
                                </select>
                                <p v-if="gutForm.errors.transporter_type_code" :class="errorClass">{{ gutForm.errors.transporter_type_code }}</p>
                            </label>
                            <div v-else class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{{ gutForm.urgency_matrix_code ? 'Matriz geral de Função Estrutural selecionada.' : 'Selecione a matriz para informar o elemento estrutural.' }}</div>
                            <div class="md:col-span-2">
                                <span :class="labelClass">Elemento</span>
                                <GutScoreSelect
                                    v-model="gutForm.urgency_option_code"
                                    :options="recUrgencyOptions"
                                    criterion="U"
                                    aria-label="Elemento da matriz de urgência REC"
                                    placeholder="Selecione o elemento"
                                    :disabled="!gutForm.urgency_matrix_code || (gutForm.urgency_matrix_code === 'patio_port_transporter' && !gutForm.transporter_type_code)"
                                />
                                <p v-if="gutForm.errors.urgency_option_code" :class="errorClass">{{ gutForm.errors.urgency_option_code }}</p>
                            </div>
                        </div>
                        <div class="mt-4 rounded-xl bg-teal-50 p-4">
                            <p :class="labelClass">Urgência (U)</p>
                            <p class="mt-1 text-xl font-bold text-teal-900">{{ recUrgencyPreview?.score ?? '—' }}</p>
                            <p class="mt-1 text-xs text-slate-600">Calculada automaticamente pela matriz e pelo elemento selecionados.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <template v-if="isCivil">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <span :class="labelClass">Tipo de degradação</span>
                                    <GutScoreSelect
                                        v-model="gutForm.trend_group_code"
                                        :options="gut_definition.trend_groups"
                                        aria-label="Tipo de degradação"
                                        placeholder="Selecione o tipo"
                                        @change="changeTrendGroup"
                                    />
                                    <p v-if="gutForm.errors.trend_group_code" :class="errorClass">{{ gutForm.errors.trend_group_code }}</p>
                                </div>
                                <div>
                                    <span :class="labelClass">Condição técnica</span>
                                    <GutScoreSelect
                                        v-model="gutForm.trend_option_code"
                                        :options="trendOptions"
                                        criterion="T"
                                        aria-label="Condição técnica CIVIL"
                                        placeholder="Selecione a condição"
                                        :disabled="!gutForm.trend_group_code"
                                    />
                                    <p v-if="gutForm.errors.trend_option_code" :class="errorClass">{{ gutForm.errors.trend_option_code }}</p>
                                </div>
                            </div>
                            <div class="mt-4 rounded-xl bg-teal-50 p-4">
                                <p :class="labelClass">Tendência (T)</p>
                                <p class="mt-1 text-xl font-bold text-teal-900">{{ civilTrendPreview?.score ?? '—' }}</p>
                                <p class="mt-1 text-xs text-slate-600">Calculada automaticamente pela condição técnica selecionada.</p>
                            </div>
                        </template>
                        <template v-else-if="isRec">
                            <div class="grid gap-4 md:grid-cols-2">
                                <label>
                                    <span :class="labelClass">Dano</span>
                                    <select v-model="gutForm.trend_group_code" :class="inputClass" @change="changeTrendGroup">
                                        <option value="">Selecione o dano</option>
                                        <option v-for="group in gut_definition.trend_groups" :key="group.code" :value="group.code">{{ group.label }}</option>
                                    </select>
                                    <p v-if="gutForm.errors.trend_group_code" :class="errorClass">{{ gutForm.errors.trend_group_code }}</p>
                                </label>
                                <div>
                                    <span :class="labelClass">Condição do dano</span>
                                    <GutScoreSelect
                                        v-model="gutForm.trend_option_code"
                                        :options="trendOptions"
                                        criterion="T"
                                        aria-label="Condição do dano"
                                        placeholder="Selecione a condição"
                                        :disabled="!gutForm.trend_group_code"
                                    />
                                    <p v-if="gutForm.errors.trend_option_code" :class="errorClass">{{ gutForm.errors.trend_option_code }}</p>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div>
                                <span :class="labelClass">Grau de oxidação ASTM D610</span>
                                <GutScoreSelect
                                    v-model="gutForm.trend_option_code"
                                    :options="gut_definition.trend_options"
                                    criterion="T"
                                    aria-label="Grau de oxidação ASTM D610"
                                    placeholder="Selecione o grau"
                                />
                                <p v-if="gutForm.errors.trend_option_code" :class="errorClass">{{ gutForm.errors.trend_option_code }}</p>
                            </div>
                        </template>
                    </div>
                </div>
                <div v-if="editing.gut" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado automático</p>
                    <p v-if="gutScorePreview !== null" class="mt-1 font-semibold text-slate-900">
                        <template v-if="technicalGutPreview">G {{ technicalGutPreview.gravity }} × U {{ technicalGutPreview.urgency }} × T {{ technicalGutPreview.trend }} = </template>
                        {{ gutScorePreview }} · {{ previewClassification ? `${previewClassification.code} — ${previewClassification.name}` : 'Sem classificação para este resultado GUT' }}
                    </p>
                    <p v-else class="mt-1 text-slate-600">Preencha os critérios técnicos para calcular o resultado.</p>
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
                        <ul v-if="item.details.length" class="mt-3 space-y-1 text-xs text-slate-600">
                            <li v-for="detail in item.details" :key="detail">{{ detail }}</li>
                        </ul>
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

            <section v-if="requiresGut && isTel" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-600">02 · Classificação TEL</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Telhado/Tapamento</h2>
                        <p class="mt-1 text-sm text-slate-500">A altura define o impacto; o dano e a condição definem o risco de queda.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="tel_snapshot" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">Snapshot salvo</span>
                        <button v-if="capabilities.tel_url && !editing.tel" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="startEditing('tel')">Editar</button>
                    </div>
                </div>
                <div v-if="isInherited && previous_assessment_summary?.tel" class="mt-5 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-slate-700">
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-800">Última revisão · {{ previous_assessment_summary.inspection.number || '—' }} · {{ previous_assessment_summary.assessed_at || 'Data não informada' }}</p>
                    <p class="mt-2"><strong class="text-slate-900">Classificação:</strong> {{ previous_assessment_summary.classification?.code || '—' }} · {{ previous_assessment_summary.classification?.label || 'Sem classificação' }}</p>
                    <p class="mt-1"><strong class="text-slate-900">TEL:</strong> Impacto {{ previous_assessment_summary.tel.impact?.score ?? '—' }} · Risco {{ previous_assessment_summary.tel.fall_risk?.score ?? '—' }} · Pontuação {{ previous_assessment_summary.tel.score ?? '—' }}</p>
                </div>
                <div v-if="editing.tel" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 md:grid-cols-2">
                    <label>
                        <span :class="labelClass">Altura do elemento (m)</span>
                        <input v-model="telForm.height_m" type="number" inputmode="decimal" min="0" step="any" :class="inputClass" @keydown="blockInvalidNumberKey">
                        <p v-if="telForm.errors.height_m" :class="errorClass">{{ telForm.errors.height_m }}</p>
                    </label>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p :class="labelClass">Impacto na Segurança</p>
                        <p class="mt-2 text-xl font-bold text-slate-900">{{ telPreview?.impact ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">Calculado automaticamente pela altura.</p>
                    </div>
                    <label>
                        <span :class="labelClass">Tipo de dano</span>
                        <select v-model="telForm.damage_group_code" :class="inputClass" @change="changeTelDamageGroup">
                            <option value="">Selecione o tipo de dano</option>
                            <option v-for="group in tel_definition?.damage_groups ?? []" :key="group.code" :value="group.code">{{ group.label }}</option>
                        </select>
                        <p v-if="telForm.errors.damage_group_code" :class="errorClass">{{ telForm.errors.damage_group_code }}</p>
                    </label>
                    <div>
                        <span :class="labelClass">Condição encontrada</span>
                        <GutScoreSelect v-model="telForm.damage_option_code" :options="telDamageOptions" criterion="Risco" aria-label="Condição encontrada" placeholder="Selecione a condição" :disabled="!telForm.damage_group_code" />
                        <p v-if="telForm.errors.damage_option_code" :class="errorClass">{{ telForm.errors.damage_option_code }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p :class="labelClass">Risco de Queda de Materiais</p>
                        <p class="mt-2 text-xl font-bold text-slate-900">{{ telPreview?.risk ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl bg-teal-50 p-4">
                        <p :class="labelClass">Pontuação e classificação</p>
                        <p v-if="telPreview" class="mt-2 font-bold text-teal-900">{{ telPreview.impact }} × {{ telPreview.risk }} = {{ telPreview.score }} · {{ telPreviewClassification?.code ?? '—' }}</p>
                        <p v-else class="mt-2 text-sm text-slate-600">Preencha os campos para calcular.</p>
                    </div>
                    <div class="col-span-full flex justify-end gap-3">
                        <button type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="cancelEditing('tel')">Cancelar</button>
                        <button type="button" :disabled="telForm.processing || !telReady" class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" @click="saveTel">Salvar classificação TEL</button>
                    </div>
                </div>
                <div v-else class="mt-5 grid gap-3 border-t border-slate-100 pt-5 md:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Impacto</p><p class="mt-2 text-xl font-bold">{{ tel_snapshot?.impact?.score ?? '—' }}</p></div>
                    <div class="rounded-2xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Risco</p><p class="mt-2 text-xl font-bold">{{ tel_snapshot?.fall_risk?.score ?? '—' }}</p></div>
                    <div class="rounded-2xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Pontuação TEL</p><p class="mt-2 text-xl font-bold">{{ assessment.tel_score ?? '—' }}</p></div>
                    <div class="rounded-2xl border border-slate-200 p-4"><p class="text-xs font-semibold text-slate-500">Classificação</p><p class="mt-2 font-bold">{{ classificationDisplay.code || '—' }}</p><p class="mt-1 text-sm text-slate-600">{{ classificationDisplay.label || 'Não classificada' }}</p></div>
                    <p v-if="tel_snapshot" class="col-span-full text-sm text-slate-600">{{ tel_snapshot.damage_group?.label }} · {{ tel_snapshot.damage_option?.label }} · Altura: {{ tel_snapshot.height_m }} m</p>
                    <p v-else class="col-span-full text-sm text-slate-500">Ainda não calculado.</p>
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
                        <p v-if="requiresEvidence" class="mt-1.5 text-xs text-slate-500">Obrigatória para publicar a avaliação.</p>
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
                <div v-if="isInherited && previous_assessment_summary && (previous_assessment_summary.comment || previous_assessment_summary.recommendation)" class="mt-4 rounded-2xl border border-teal-200 bg-teal-50 p-4 text-sm text-slate-700">
                    <p class="text-xs font-bold uppercase tracking-wide text-teal-800">Última revisão · {{ previous_assessment_summary.inspection.number || '—' }} · {{ previous_assessment_summary.assessed_at || 'Data não informada' }}</p>
                    <p v-if="previous_assessment_summary.comment" class="mt-2 whitespace-pre-line"><strong class="text-slate-900">Comentário técnico:</strong> {{ previous_assessment_summary.comment }}</p>
                    <p v-if="previous_assessment_summary.recommendation" class="mt-2 whitespace-pre-line"><strong class="text-slate-900">Recomendação:</strong> {{ previous_assessment_summary.recommendation }}</p>
                </div>
            </section>

            <section v-if="requiresEvidence" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">05 · Mapa e localização</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Localização visual da avaria</h2>
                        <p class="mt-1 text-sm text-slate-500">Envie o mapa e identifique uma ou mais regiões da mesma avaria. Obrigatório para publicar.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <span class="h-3 w-3 rounded-full border border-black/10" :style="{ backgroundColor: location_map?.color || '#64748B' }"></span>
                        Cor automática da classificação
                    </span>
                </div>

                <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <div :class="locationPreviewMap ? '' : 'aspect-[4/3]'">
                            <InspectionLocationReportMap v-if="locationPreviewMap" :map="locationPreviewMap" />
                            <img v-else-if="location_map?.background_url" :src="location_map.background_url" :alt="`Mapa da avaria ${assessment.defect?.code}`" class="h-full w-full object-contain">
                            <img v-else-if="mapPreviewUrl" :src="mapPreviewUrl" alt="Prévia do mapa selecionado" class="h-full w-full object-contain">
                            <div v-else class="flex h-full items-center justify-center p-6 text-center text-sm text-slate-500">Nenhuma imagem de mapa enviada.</div>
                        </div>
                        <div v-if="location_map" class="border-t border-slate-200 bg-white p-4 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold text-slate-900">Versão {{ location_map.version }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="location_map.processing_status === 'ready' ? 'bg-emerald-100 text-emerald-800' : location_map.processing_status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'">
                                    {{ location_map.processing_status === 'ready' ? 'Disponível' : location_map.processing_status === 'failed' ? 'Falha' : 'Processando' }}
                                </span>
                            </div>
                            <p v-if="location_map.processing_error" class="mt-2 text-xs text-rose-700">{{ location_map.processing_error }}</p>
                            <p v-if="location_map.location?.confirmed" class="mt-2 text-xs font-semibold text-emerald-700">Localização confirmada.</p>
                            <p v-else-if="location_map.location" class="mt-2 text-xs font-semibold text-amber-700">Localização herdada; confirme ou ajuste antes de publicar.</p>
                            <p v-else-if="location_map.processing_status === 'ready'" class="mt-2 text-xs font-semibold text-amber-700">Identifique a localização antes de publicar.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <form v-if="capabilities.location_map_upload_url && !locationModalOpen" class="rounded-2xl border border-slate-200 p-4" @submit.prevent="uploadMap">
                            <h3 class="text-sm font-semibold text-slate-950">{{ location_map ? 'Substituir imagem' : 'Enviar mapa' }}</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">PNG, JPEG ou WEBP, até 50 MB. Uma substituição preserva as versões usadas em avaliações anteriores.</p>
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="mt-3 block w-full text-xs" @change="selectMapFile">
                            <p v-if="mapForm.errors.file" class="mt-2 text-xs font-medium text-rose-700">{{ mapForm.errors.file }}</p>
                            <button type="submit" :disabled="!mapForm.file || mapForm.processing" class="mt-3 w-full rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                                {{ mapForm.processing ? 'Enviando…' : (location_map ? 'Criar nova versão' : 'Enviar mapa') }}
                            </button>
                        </form>
                        <button v-if="locationEditorMap" type="button" class="block w-full rounded-xl bg-slate-950 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-teal-700" @click="locationModalOpen = true">
                            {{ location_map.location ? (location_map.location.confirmed ? 'Editar localização' : 'Confirmar localização') : 'Identificar localização' }}
                        </button>
                        <Link v-if="location_map?.editor_url" :href="location_map.editor_url" class="block rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:border-slate-400">
                            Abrir editor ampliado
                        </Link>
                        <button v-if="capabilities.location_map_delete_url && !locationModalOpen" type="button" class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700" @click="removeMap">Remover desta avaliação</button>
                    </div>
                </div>
            </section>

            <section v-if="requiresEvidence" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">06 · Registros fotográficos</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-950">Documentação da avaria</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ evidence.length }} item(ns)</span>
                        <button v-if="isInherited && previous_assessment_summary?.photos?.length" type="button" class="rounded-xl border border-teal-300 bg-teal-50 px-3.5 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-100" @click="historyOpen = true">Ver evidências anteriores</button>
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
        <AssessmentLocationModal
            v-if="locationEditorMap"
            :open="locationModalOpen"
            :assessment="assessment"
            :map="locationEditorMap"
            @close="locationModalOpen = false"
        />
    </AppLayout>
</template>
