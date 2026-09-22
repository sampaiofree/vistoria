<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AssessmentLocationWorkspace from './AssessmentLocationWorkspace.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    assessment: { type: Object, required: true },
    map: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved', 'deleted']);
const dialog = ref(null);
const workspace = ref(null);
let previousFocus = null;

function close() {
    if (workspace.value?.isDirty && !window.confirm('Descartar as alterações não salvas da localização?')) return;
    workspace.value?.discardChanges();
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

function saved() {
    emit('saved');
    emit('close');
}

function deleted() {
    emit('deleted');
    emit('close');
}

watch(() => props.open, async (open) => {
    if (typeof document === 'undefined') return;
    if (open) {
        previousFocus = document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        dialog.value?.focus();
        return;
    }
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
    previousFocus?.focus?.();
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" @click.self="close">
            <section ref="dialog" role="dialog" aria-modal="true" :aria-label="`Localização da avaria ${assessment.defect?.code ?? assessment.defect_code}`" tabindex="-1" class="max-h-[94vh] w-full max-w-7xl overflow-y-auto rounded-3xl bg-slate-50 shadow-2xl outline-none">
                <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Mapa e localização</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">{{ assessment.defect?.code ?? assessment.defect_code }} · {{ assessment.defect?.title ?? assessment.defect_title }}</h2>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="close">Fechar</button>
                </header>
                <div class="p-5 sm:p-6">
                    <AssessmentLocationWorkspace
                        ref="workspace"
                        :map="map"
                        :location="map.location"
                        :color="map.color"
                        :photo-legend="map.photo_legend"
                        :update-url="map.update_url"
                        :delete-url="map.delete_url"
                        @saved="saved"
                        @deleted="deleted"
                    />
                </div>
            </section>
        </div>
    </Teleport>
</template>
