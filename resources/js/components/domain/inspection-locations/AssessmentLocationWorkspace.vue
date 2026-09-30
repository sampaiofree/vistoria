<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import InspectionLocationMapEditor from '@/components/domain/inspection-locations/InspectionLocationMapEditor.vue';
import InspectionLocationToolbar from '@/components/domain/inspection-locations/InspectionLocationToolbar.vue';

const props = defineProps({
    map: { type: Object, required: true },
    location: { type: Object, default: null },
    color: { type: String, default: '#64748B' },
    photoLegend: { type: String, default: '0 FOTOS PRONTAS' },
    updateUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
});

const emit = defineEmits(['saved', 'deleted']);
const editor = ref(null);
const initialGeometry = props.location?.geometry ?? { version: 1, shapes: [] };
const initialLabel = props.location?.label ?? '';
const geometry = ref(JSON.parse(JSON.stringify(initialGeometry)));
const label = ref(initialLabel);
const activeTool = ref(props.location ? 'select' : 'rectangle');
const selectedShapeIndex = ref(null);
const zoom = ref(1);
const workingPath = ref(false);
const saving = ref(false);
const deleting = ref(false);
const errors = ref({});
const initialValue = JSON.stringify({ geometry: initialGeometry, label: initialLabel });
const style = computed(() => ({
    stroke: props.color,
    fill: props.color,
    stroke_width: 0.005,
    opacity: 0.9,
    dashed: false,
}));
const hasGeometry = computed(() => (geometry.value?.shapes?.length ?? 0) > 0);
const isDirty = computed(() => JSON.stringify({ geometry: geometry.value, label: label.value }) !== initialValue);

function tool(value) {
    activeTool.value = value;
    selectedShapeIndex.value = null;
    editor.value?.cancelPath();
}

function selectShape(index) {
    activeTool.value = 'select';
    editor.value?.cancelPath();
    selectedShapeIndex.value = index;
}

function updateGeometry(value) {
    geometry.value = value;
    selectedShapeIndex.value = null;
}

function removeSelectedShape() {
    const index = selectedShapeIndex.value;
    if (index === null || !geometry.value.shapes?.[index]) return;
    geometry.value = {
        ...geometry.value,
        shapes: geometry.value.shapes.filter((_, shapeIndex) => shapeIndex !== index),
    };
    selectedShapeIndex.value = null;
}

function shapeLabel(type) {
    return { rectangle: 'Área', point: 'Ponto', polygon: 'Polígono', polyline: 'Linha' }[type] ?? 'Forma';
}

function save() {
    if (!hasGeometry.value || saving.value) return;
    saving.value = true;
    errors.value = {};
    router.put(props.updateUrl, {
        geometry: geometry.value,
        label: label.value.trim() || null,
        lock_version: props.location?.lock_version ?? null,
    }, {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
        onError: (value) => { errors.value = value; },
        onFinish: () => { saving.value = false; },
    });
}

function removeLocation() {
    if (!props.deleteUrl || deleting.value || !window.confirm('Remover a localização desta avaliação?')) return;
    deleting.value = true;
    router.delete(props.deleteUrl, {
        data: { lock_version: props.location.lock_version },
        preserveScroll: true,
        onSuccess: () => emit('deleted'),
        onFinish: () => { deleting.value = false; },
    });
}

function discardChanges() {
    editor.value?.cancelPath();
}

defineExpose({ isDirty, discardChanges });
</script>

<template>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <main class="min-w-0 space-y-3">
            <InspectionLocationToolbar
                :active-tool="activeTool"
                :zoom="zoom"
                :has-working-path="workingPath"
                drawing
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
                :markers="[]"
                :geometry="geometry"
                :style="style"
                :label="label"
                :photo-legend="photoLegend"
                :active-tool="activeTool"
                :selected-shape-index="selectedShapeIndex"
                @update:geometry="updateGeometry"
                @zoom="zoom = $event"
                @working-path="workingPath = $event"
                @select-shape="selectShape"
            />
            <p class="rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">Use as ferramentas para marcar todos os pontos ou regiões desta avaria. Para corrigir, clique em Selecionar, escolha a marcação e use Excluir área selecionada. A legenda das fotos é atualizada automaticamente.</p>
        </main>

        <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Mapa versão {{ map.version }}</p>
            <h2 class="mt-1 font-semibold text-slate-950">{{ location?.confirmed ? 'Localização confirmada' : location ? 'Confirme a localização herdada' : 'Identifique a avaria' }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">{{ geometry.shapes?.length || 0 }} região(ões) marcada(s).</p>
            <div v-if="hasGeometry" class="mt-3 flex max-h-32 flex-wrap gap-2 overflow-y-auto" role="group" aria-label="Marcações da localização">
                <button v-for="(shape, index) in geometry.shapes" :key="index" type="button" class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold" :class="selectedShapeIndex === index ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-400'" :aria-pressed="selectedShapeIndex === index" @click="selectShape(index)">
                    {{ shapeLabel(shape.type) }} {{ index + 1 }}
                </button>
            </div>
            <button v-if="hasGeometry" type="button" :disabled="selectedShapeIndex === null || saving" class="mt-3 w-full rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50" @click="removeSelectedShape">Excluir área selecionada</button>
            <p v-else class="mt-2 text-xs text-amber-700">Desenhe uma nova marcação antes de salvar.</p>

            <label class="mt-4 block">
                <span class="text-sm font-semibold text-slate-800">Legenda adicional <span class="font-normal text-slate-500">(opcional)</span></span>
                <textarea v-model="label" rows="4" maxlength="240" class="mt-1.5 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Observação curta para o mapa."></textarea>
                <span class="mt-1 block text-right text-[11px] text-slate-500">{{ label.length }}/240</span>
            </label>

            <div v-if="Object.keys(errors).length" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs text-rose-700" role="alert">
                <p v-for="(message, key) in errors" :key="key">{{ message }}</p>
            </div>

            <button type="button" :disabled="!hasGeometry || saving" class="mt-4 w-full rounded-xl bg-teal-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" @click="save">
                {{ saving ? 'Salvando…' : (location?.confirmed ? 'Salvar localização' : 'Confirmar localização') }}
            </button>
            <button v-if="deleteUrl" type="button" :disabled="deleting" class="mt-2 w-full rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 disabled:opacity-50" @click="removeLocation">
                {{ deleting ? 'Removendo…' : 'Remover localização' }}
            </button>
        </aside>
    </div>
</template>
