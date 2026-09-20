<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionLocationMapEditor from '@/components/domain/inspection-locations/InspectionLocationMapEditor.vue';
import InspectionLocationToolbar from '@/components/domain/inspection-locations/InspectionLocationToolbar.vue';

const props = defineProps({
    assessment: { type: Object, required: true },
    map: { type: Object, required: true },
    location: { type: Object, default: null },
    update_url: { type: String, required: true },
    delete_url: { type: String, default: null },
});

const editor = ref(null);
const geometry = ref(props.location?.geometry ?? { version: 1, shapes: [] });
const label = ref(props.location?.label ?? '');
const activeTool = ref(props.location ? 'select' : 'rectangle');
const zoom = ref(1);
const workingPath = ref(false);
const saving = ref(false);
const deleting = ref(false);
const errors = ref({});
const style = computed(() => ({
    stroke: props.assessment.color,
    fill: props.assessment.color,
    stroke_width: 0.005,
    opacity: 0.9,
    dashed: false,
}));
const hasGeometry = computed(() => (geometry.value?.shapes?.length ?? 0) > 0);

function tool(value) {
    activeTool.value = value;
    editor.value?.cancelPath();
}

function save() {
    if (!hasGeometry.value || saving.value) return;
    saving.value = true;
    errors.value = {};
    router.put(props.update_url, {
        geometry: geometry.value,
        label: label.value.trim() || null,
        lock_version: props.location?.lock_version ?? null,
    }, {
        preserveScroll: true,
        onError: (value) => { errors.value = value; },
        onFinish: () => { saving.value = false; },
    });
}

function removeLocation() {
    if (!props.delete_url || deleting.value || !window.confirm('Remover a localização desta avaliação?')) return;
    deleting.value = true;
    router.delete(props.delete_url, {
        data: { lock_version: props.location.lock_version },
        onFinish: () => { deleting.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="`Localização · ${assessment.defect_code}`" :subtitle="assessment.defect_title" wide>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link :href="assessment.show_url" class="text-sm font-semibold text-teal-700">← Voltar à avaliação</Link>
                <p class="mt-1 text-xs text-slate-500">Desenhe uma ou mais regiões da mesma avaria. O vínculo e a cor são automáticos.</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                <span class="h-3.5 w-3.5 rounded-full border border-black/10" :style="{ backgroundColor: assessment.color }"></span>
                {{ assessment.category.code }} · cor do GUT
            </span>
        </div>

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
                    :photo-legend="assessment.photo_legend"
                    :active-tool="activeTool"
                    @update:geometry="geometry = $event"
                    @zoom="zoom = $event"
                    @working-path="workingPath = $event"
                />
                <p class="rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">Use as ferramentas para marcar todos os pontos ou regiões desta avaria. A legenda das fotos é incluída automaticamente.</p>
            </main>

            <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Mapa versão {{ map.version }}</p>
                <h2 class="mt-1 font-semibold text-slate-950">{{ location?.confirmed ? 'Localização confirmada' : location ? 'Confirme a localização herdada' : 'Identifique a avaria' }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ geometry.shapes?.length || 0 }} região(ões) marcada(s).</p>

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
                <button v-if="delete_url" type="button" :disabled="deleting" class="mt-2 w-full rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 disabled:opacity-50" @click="removeLocation">
                    {{ deleting ? 'Removendo…' : 'Remover localização' }}
                </button>
            </aside>
        </div>
    </AppLayout>
</template>
