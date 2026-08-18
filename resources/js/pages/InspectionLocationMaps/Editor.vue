<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionLocationMapEditor from '@/components/domain/inspection-locations/InspectionLocationMapEditor.vue';
import InspectionLocationToolbar from '@/components/domain/inspection-locations/InspectionLocationToolbar.vue';
import InspectionLocationMarkerPanel from '@/components/domain/inspection-locations/InspectionLocationMarkerPanel.vue';
import InspectionLocationAssessmentPicker from '@/components/domain/inspection-locations/InspectionLocationAssessmentPicker.vue';
import {
    DEFAULT_MARKER_COLOR,
    isMarkerColor,
    markerSupportsOptionalBorder,
    normalizeMarkerColor,
    normalizeMarkerStyle,
} from '@/components/domain/inspection-locations/inspectionLocationMarkerStyle.js';

const props = defineProps({
    map: { type: Object, required: true },
    markers: { type: Array, default: () => [] },
    assessments: { type: Array, default: () => [] },
    back_url: { type: String, required: true },
    defects_url: { type: String, required: true },
});

const editor = ref(null);
const detailsPanel = ref(null);
const selectedMarkerPublicId = ref(null);
const stage = ref('browse');
const selectedAssessmentId = ref(null);
const geometry = ref({ version: 1, shapes: [] });
const style = ref(defaultStyle());
const colorInput = ref(DEFAULT_MARKER_COLOR);
const showBorder = ref(true);
const label = ref('');
const position = ref((props.markers.length || 0) + 1);
const activeTool = ref('select');
const zoom = ref(1);
const workingPath = ref(false);
const errors = ref({});
const saving = ref(false);
const deleting = ref(false);
const originalMarker = ref(null);

const selectedMarker = computed(() => props.markers.find((item) => item.public_id === selectedMarkerPublicId.value) || null);
const selectedAssessment = computed(() => props.assessments.find((item) => item.id === selectedAssessmentId.value) || null);
const availableAssessments = computed(() => props.assessments.filter((item) => item.is_available
    || (selectedMarker.value && item.id === selectedMarker.value.defect_assessment_id)));
const hasGeometry = computed(() => geometry.value.shapes?.length > 0);
const hasAvailableAssessments = computed(() => props.assessments.some((item) => item.is_available));
const canSave = computed(() => stage.value === 'link' && selectedAssessmentId.value && hasGeometry.value && !saving.value);
const canSaveDesign = computed(() => stage.value === 'design' && selectedMarker.value && selectedAssessmentId.value && hasGeometry.value && isMarkerColor(colorInput.value) && !saving.value);
const canContinueDesign = computed(() => hasGeometry.value && isMarkerColor(colorInput.value));
const borderCanBeDisabled = computed(() => markerSupportsOptionalBorder(geometry.value));
const activePhotoLegend = computed(() => {
    if (selectedMarker.value && selectedMarker.value.defect_assessment_id === selectedAssessmentId.value) {
        return selectedMarker.value.photo_legend || 'FOTOS: —';
    }

    return selectedAssessment.value?.photo_legend || 'FOTOS: —';
});
const hasConcurrencyError = computed(() => ['lock_version', 'map_lock_version']
    .some((key) => Object.prototype.hasOwnProperty.call(errors.value, key)));
const stageTitle = computed(() => ({
    browse: 'Marcações do mapa',
    draw: '1. Marcação',
    design: '2. Design da marcação',
    link: '3. Vincular avaria',
    edit: 'Marcação selecionada',
}[stage.value]));
const stageNumber = computed(() => ({ draw: 1, design: 2, link: 3 }[stage.value] || null));

function defaultStyle() {
    return {
        stroke: DEFAULT_MARKER_COLOR,
        fill: DEFAULT_MARKER_COLOR,
        stroke_width: 0.005,
        opacity: 0.9,
        dashed: false,
    };
}

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function loadDesign(value) {
    style.value = normalizeMarkerStyle(value, geometry.value);
    colorInput.value = style.value.fill;
    showBorder.value = style.value.stroke !== 'none';
}

function applyDesign() {
    if (!isMarkerColor(colorInput.value)) return false;

    const color = normalizeMarkerColor(colorInput.value);
    colorInput.value = color;
    if (!borderCanBeDisabled.value) showBorder.value = true;
    style.value = {
        ...normalizeMarkerStyle(style.value, geometry.value),
        stroke: showBorder.value ? color : 'none',
        fill: color,
    };

    return true;
}

function setDesignColor(value) {
    colorInput.value = value.toUpperCase();
    if (isMarkerColor(colorInput.value)) applyDesign();
}

function toggleBorder(value) {
    showBorder.value = borderCanBeDisabled.value ? value : true;
    applyDesign();
}

function snapshotMarker(marker) {
    return {
        geometry: clone(marker.geometry),
        style: normalizeMarkerStyle(marker.style, marker.geometry),
        label: marker.label || '',
        position: marker.position,
        selectedAssessmentId: marker.defect_assessment_id,
    };
}

function resetToBrowse() {
    editor.value?.cancelPath();
    selectedMarkerPublicId.value = null;
    originalMarker.value = null;
    stage.value = 'browse';
    activeTool.value = 'select';
    geometry.value = { version: 1, shapes: [] };
    loadDesign(defaultStyle());
    selectedAssessmentId.value = null;
    label.value = '';
    position.value = props.markers.length + 1;
    workingPath.value = false;
    errors.value = {};
}

function selectMarker(marker) {
    selectedMarkerPublicId.value = marker.public_id;
    originalMarker.value = snapshotMarker(marker);
    selectedAssessmentId.value = marker.defect_assessment_id;
    geometry.value = clone(marker.geometry);
    loadDesign(marker.style);
    label.value = marker.label || '';
    position.value = marker.position;
    errors.value = {};
    activeTool.value = 'select';
    stage.value = 'edit';
}

async function newMarker() {
    if (!hasAvailableAssessments.value) return;

    selectedMarkerPublicId.value = null;
    originalMarker.value = null;
    selectedAssessmentId.value = null;
    geometry.value = { version: 1, shapes: [] };
    loadDesign(defaultStyle());
    label.value = '';
    position.value = props.markers.length + 1;
    errors.value = {};
    activeTool.value = 'rectangle';
    stage.value = 'draw';

    await nextTick();
    detailsPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function updateGeometry(value) {
    geometry.value = value;

    if (stage.value === 'draw' && value.shapes?.length > 0) {
        activeTool.value = 'select';
        loadDesign(style.value);
        stage.value = 'design';
        nextTick(() => detailsPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
    }
}

function selectAssessment(assessment) {
    selectedAssessmentId.value = assessment.id;
}

function redrawMarker() {
    geometry.value = { version: 1, shapes: [] };
    activeTool.value = 'rectangle';
    stage.value = 'draw';
    errors.value = {};
    editor.value?.cancelPath();
}

function addRegion() {
    activeTool.value = 'rectangle';
    stage.value = 'draw';
    errors.value = {};
    editor.value?.cancelPath();
}

function editDesign() {
    if (!hasGeometry.value) {
        redrawMarker();
        return;
    }

    loadDesign(style.value);
    activeTool.value = 'select';
    errors.value = {};
    stage.value = 'design';
}

function continueToLink() {
    if (!canContinueDesign.value || !applyDesign()) return;

    activeTool.value = 'select';
    errors.value = {};
    stage.value = 'link';
}

function backToMarking() {
    activeTool.value = 'rectangle';
    errors.value = {};
    stage.value = 'draw';
}

function goToDesign() {
    if (!hasGeometry.value) return;

    editor.value?.cancelPath();
    activeTool.value = 'select';
    loadDesign(style.value);
    errors.value = {};
    stage.value = 'design';
}

function changeAssessment() {
    if (!hasGeometry.value) {
        redrawMarker();
        return;
    }

    activeTool.value = 'select';
    errors.value = {};
    stage.value = 'link';
}

function cancelFlow() {
    editor.value?.cancelPath();
    errors.value = {};

    if (selectedMarker.value && originalMarker.value) {
        geometry.value = clone(originalMarker.value.geometry);
        loadDesign(originalMarker.value.style);
        label.value = originalMarker.value.label;
        position.value = originalMarker.value.position;
        selectedAssessmentId.value = originalMarker.value.selectedAssessmentId;
        stage.value = 'edit';
        activeTool.value = 'select';
        return;
    }

    resetToBrowse();
}

function save(designOnly = false) {
    const onlyDesign = designOnly === true;
    if (!(onlyDesign ? canSaveDesign.value : canSave.value) || !applyDesign()) return;

    saving.value = true;
    errors.value = {};
    const data = {
        defect_assessment_id: selectedAssessmentId.value,
        label: label.value.trim() || null,
        geometry: geometry.value,
        style: style.value,
        position: position.value,
        map_lock_version: props.map.lock_version,
    };
    const options = {
        preserveScroll: true,
        onSuccess: resetToBrowse,
        onError: (value) => { errors.value = value; },
        onFinish: () => { saving.value = false; },
    };

    if (selectedMarker.value) {
        router.put(selectedMarker.value.update_url, {
            ...data,
            lock_version: selectedMarker.value.lock_version,
        }, options);
        return;
    }

    router.post(props.map.store_marker_url, data, options);
}

function removeMarker() {
    const marker = selectedMarker.value;

    if (!marker || deleting.value || !window.confirm('Remover esta marcação?')) return;

    deleting.value = true;
    errors.value = {};
    router.delete(marker.delete_url, {
        data: {
            lock_version: marker.lock_version,
            map_lock_version: props.map.lock_version,
        },
        preserveScroll: true,
        onSuccess: resetToBrowse,
        onError: (value) => { errors.value = value; },
        onFinish: () => { deleting.value = false; },
    });
}

function reloadEditor() {
    router.reload({
        preserveScroll: true,
        onSuccess: resetToBrowse,
    });
}

function tool(value) {
    activeTool.value = value;
    editor.value?.cancelPath();
}
</script>

<template>
    <AppLayout :title="`Editor · ${map.title}`" :subtitle="`${map.category.code} — ${map.category.name}`" wide>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link :href="back_url" class="text-sm font-semibold text-teal-700">← Voltar ao mapa</Link>
                <p class="mt-1 text-xs text-slate-500">Adicione uma marcação visual e vincule a avaria correspondente.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ markers.length }} marcação(ões)</span>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <main class="min-w-0 space-y-3">
                <InspectionLocationToolbar
                    :active-tool="activeTool"
                    :zoom="zoom"
                    :has-working-path="workingPath"
                    :drawing="stage === 'draw'"
                    @tool="tool"
                    @zoom-in="editor?.setZoom(zoom + 0.25)"
                    @zoom-out="editor?.setZoom(zoom - 0.25)"
                    @reset-view="editor?.resetView()"
                    @finish-path="editor?.finishPath()"
                    @cancel-path="editor?.cancelPath()"
                />
                <InspectionLocationMapEditor
                    ref="editor"
                    :background-url="map.background_url"
                    :background-width="map.background_width"
                    :background-height="map.background_height"
                    :markers="markers.filter((item) => item.public_id !== selectedMarkerPublicId)"
                    :geometry="geometry"
                    :style="style"
                    :label="label"
                    :photo-legend="activePhotoLegend"
                    :active-tool="activeTool"
                    :selected-public-id="selectedMarkerPublicId"
                    @update:geometry="updateGeometry"
                    @zoom="zoom = $event"
                    @working-path="workingPath = $event"
                    @select-marker="selectMarker"
                />
                <p v-if="stage === 'draw'" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" role="status">
                    Desenhe a área da avaria sobre o mapa. Ao terminar a forma, você definirá o design da marcação.
                </p>
                <p v-else-if="stage === 'design'" class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs text-violet-900" role="status">
                    Escolha a cor e defina se as áreas fechadas terão borda. A prévia é atualizada no mapa.
                </p>
                <p v-else-if="stage === 'link'" class="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs text-teal-900" role="status">
                    A marcação visual está pronta. Selecione a avaria e confira abaixo a legenda do relatório.
                </p>
                <p v-else class="rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">
                    Clique em uma marcação existente para editá-la ou use “Nova marcação” para começar.
                </p>
            </main>

            <aside ref="detailsPanel" class="scroll-mt-24">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Editor de localização</p>
                            <h2 class="mt-1 font-semibold text-slate-950">{{ stageTitle }}</h2>
                        </div>
                        <span v-if="stageNumber" class="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-bold text-teal-800">Etapa {{ stageNumber }} de 3</span>
                    </div>

                    <template v-if="stage === 'browse'">
                        <template v-if="hasAvailableAssessments">
                            <p class="text-sm leading-6 text-slate-600">Comece desenhando a região da avaria no mapa. Depois, selecione uma avaliação publicada.</p>
                            <button type="button" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2" @click="newMarker">
                                + Nova marcação
                            </button>
                        </template>
                        <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                            <p class="font-semibold">Nenhuma avaria disponível.</p>
                            <p class="mt-1 text-xs">Publique uma avaliação desta categoria ou remova um vínculo existente.</p>
                            <Link :href="defects_url" class="mt-3 inline-flex font-semibold text-amber-950 underline underline-offset-2">Ir para as avarias</Link>
                        </div>
                        <button v-if="!hasAvailableAssessments" type="button" disabled class="mt-3 w-full cursor-not-allowed rounded-xl bg-slate-200 px-4 py-3 text-sm font-semibold text-slate-500" title="Não há avaria publicada sem vínculo nesta categoria.">
                            + Nova marcação
                        </button>
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <InspectionLocationMarkerPanel :markers="markers" :selected-public-id="selectedMarkerPublicId" @select="selectMarker" />
                        </div>
                    </template>

                    <template v-else-if="stage === 'draw'">
                        <div class="rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-700">
                            <p class="font-semibold text-slate-950">Marque a região no desenho</p>
                            <p class="mt-1">A ferramenta Área já está selecionada. Arraste sobre {{ hasGeometry ? 'outra região da mesma avaria' : 'o local da avaria' }}.</p>
                        </div>
                        <button v-if="hasGeometry" type="button" class="mt-4 w-full rounded-xl border border-teal-300 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50" @click="goToDesign">Continuar para o design</button>
                        <button type="button" :class="hasGeometry ? 'mt-2' : 'mt-4'" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="cancelFlow">Cancelar</button>
                    </template>

                    <template v-else-if="stage === 'design'">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-950">Cor da marcação</p>
                            <div class="mt-3 flex items-center gap-3">
                                <input
                                    type="color"
                                    :value="isMarkerColor(colorInput) ? colorInput : DEFAULT_MARKER_COLOR"
                                    class="h-11 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                                    aria-label="Selecionar cor da marcação"
                                    @input="setDesignColor($event.target.value)"
                                >
                                <label class="min-w-0 flex-1">
                                    <span class="sr-only">Cor hexadecimal</span>
                                    <input
                                        :value="colorInput"
                                        type="text"
                                        maxlength="7"
                                        spellcheck="false"
                                        class="block w-full rounded-xl border px-3 py-2.5 font-mono text-sm uppercase outline-none focus:ring-2"
                                        :class="isMarkerColor(colorInput) ? 'border-slate-300 focus:border-teal-600 focus:ring-teal-100' : 'border-rose-300 focus:border-rose-500 focus:ring-rose-100'"
                                        placeholder="#F1DF00"
                                        @input="setDesignColor($event.target.value)"
                                        @blur="isMarkerColor(colorInput) && setDesignColor(colorInput)"
                                    >
                                </label>
                                <span class="h-10 w-10 shrink-0 rounded-lg border border-slate-300" :style="{ backgroundColor: isMarkerColor(colorInput) ? colorInput : 'transparent' }" aria-hidden="true"></span>
                            </div>
                            <p v-if="!isMarkerColor(colorInput)" class="mt-2 text-xs font-medium text-rose-700">Informe uma cor no formato #RRGGBB.</p>
                        </div>

                        <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 p-3" :class="borderCanBeDisabled ? 'cursor-pointer' : 'cursor-not-allowed bg-slate-50'">
                            <input
                                type="checkbox"
                                :checked="showBorder"
                                :disabled="!borderCanBeDisabled"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                                @change="toggleBorder($event.target.checked)"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Exibir borda</span>
                                <span class="mt-0.5 block text-xs leading-5 text-slate-500">
                                    {{ borderCanBeDisabled ? 'Desmarque para manter somente o preenchimento das áreas.' : 'A borda é obrigatória para pontos, linhas e marcações mistas com essas formas.' }}
                                </span>
                            </span>
                        </label>

                        <div v-if="Object.keys(errors).length" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-700" role="alert" aria-live="assertive">
                            <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
                            <button v-if="hasConcurrencyError" type="button" class="mt-2 font-semibold underline underline-offset-2" @click="reloadEditor">Recarregar editor</button>
                        </div>

                        <button v-if="selectedMarker" type="button" :disabled="!canSaveDesign" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :aria-busy="saving" @click="save(true)">
                            {{ saving ? 'Salvando…' : 'Salvar somente o design' }}
                        </button>
                        <button type="button" :disabled="!canContinueDesign" class="mt-2 w-full rounded-xl border border-teal-300 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-50" @click="continueToLink">
                            Continuar para vincular avaria
                        </button>
                        <button type="button" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="backToMarking">Voltar à marcação</button>
                        <button type="button" class="mt-2 w-full px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800" @click="cancelFlow">Cancelar</button>
                    </template>

                    <template v-else-if="stage === 'link'">
                        <InspectionLocationAssessmentPicker :assessments="availableAssessments" :selected-id="selectedAssessmentId" @select="selectAssessment" />

                        <label class="mt-4 block">
                            <span class="text-sm font-semibold text-slate-800">Legenda adicional <span class="font-normal text-slate-500">(opcional)</span></span>
                            <textarea v-model="label" rows="3" maxlength="240" class="mt-1.5 block w-full resize-y rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100" placeholder="Acrescente uma observação curta ao mapa."></textarea>
                            <span class="mt-1 flex items-start justify-between gap-3 text-[11px] text-slate-500">
                                <span>Será exibida abaixo da legenda automática das fotos.</span>
                                <span class="shrink-0">{{ label.length }}/240</span>
                            </span>
                        </label>

                        <div v-if="selectedAssessment" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Prévia da legenda</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ activePhotoLegend }}</p>
                            <p v-if="label.trim()" class="mt-1 whitespace-pre-line text-xs leading-5 text-slate-600">{{ label.trim() }}</p>
                        </div>

                        <button type="button" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400" @click="addRegion">
                            + Adicionar outra região à mesma marcação
                        </button>

                        <div v-if="Object.keys(errors).length" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-700" role="alert" aria-live="assertive">
                            <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
                            <button v-if="hasConcurrencyError" type="button" class="mt-2 font-semibold underline underline-offset-2" @click="reloadEditor">Recarregar editor</button>
                        </div>
                        <button type="button" :disabled="!canSave" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" :aria-busy="saving" @click="save">
                            {{ saving ? 'Salvando…' : 'Salvar marcação' }}
                        </button>
                        <button type="button" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="editDesign">Voltar ao design</button>
                        <button type="button" class="mt-2 w-full px-3 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800" @click="cancelFlow">Cancelar</button>
                    </template>

                    <template v-else>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Avaria vinculada</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ selectedMarker?.defect_code || 'Sem avaria' }}</p>
                            <p class="mt-1 text-sm leading-5 text-slate-600">{{ selectedMarker?.defect_title || 'A marcação ainda não possui uma avaria vinculada.' }}</p>
                            <div class="mt-3 border-t border-slate-200 pt-3">
                                <p class="text-xs font-semibold text-slate-800">{{ selectedMarker?.photo_legend || 'FOTOS: —' }}</p>
                                <p v-if="selectedMarker?.label" class="mt-1 text-xs leading-5 text-slate-600">{{ selectedMarker.label }}</p>
                            </div>
                        </div>
                        <div v-if="Object.keys(errors).length" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-700" role="alert" aria-live="assertive">
                            <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
                            <button v-if="hasConcurrencyError" type="button" class="mt-2 font-semibold underline underline-offset-2" @click="reloadEditor">Recarregar editor</button>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <button type="button" class="w-full rounded-xl border border-teal-300 px-3 py-2.5 text-sm font-semibold text-teal-800 hover:bg-teal-50" @click="changeAssessment">Editar avaria e legenda</button>
                            <button type="button" class="w-full rounded-xl border border-violet-300 px-3 py-2.5 text-sm font-semibold text-violet-800 hover:bg-violet-50" @click="editDesign">Alterar design</button>
                            <button type="button" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="redrawMarker">Alterar marcação visual</button>
                            <button type="button" :disabled="deleting" class="w-full rounded-xl border border-rose-200 px-3 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50" :aria-busy="deleting" @click="removeMarker">
                                {{ deleting ? 'Excluindo…' : 'Excluir marcação' }}
                            </button>
                        </div>
                        <p class="mt-4 text-xs leading-5 text-slate-500">A prévia sobre o mapa usa o mesmo posicionamento e estilo do relatório.</p>
                        <button v-if="hasAvailableAssessments" type="button" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800" @click="newMarker">+ Nova marcação</button>
                    </template>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
