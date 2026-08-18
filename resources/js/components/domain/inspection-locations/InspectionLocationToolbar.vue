<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    activeTool: { type: String, default: 'select' },
    zoom: { type: Number, default: 1 },
    hasWorkingPath: { type: Boolean, default: false },
    drawing: { type: Boolean, default: false },
});

defineEmits(['tool', 'zoom-in', 'zoom-out', 'reset-view', 'finish-path', 'cancel-path']);

const showAdvanced = ref(false);
const basicTools = [
    ['select', 'Selecionar'],
    ['pan', 'Mover'],
    ['rectangle', 'Área'],
    ['point', 'Ponto'],
];
const advancedTools = [
    ['polygon', 'Polígono'],
    ['polyline', 'Linha'],
];
const visibleTools = computed(() => props.drawing ? basicTools : basicTools.slice(0, 2));
const activeToolIsAdvanced = computed(() => advancedTools.some(([value]) => value === props.activeTool));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" role="toolbar" aria-label="Ferramentas do mapa">
        <span v-if="drawing" class="mr-1 hidden text-xs font-semibold text-slate-600 sm:inline">Desenhar:</span>
        <button v-for="tool in visibleTools" :key="tool[0]" type="button" class="rounded-xl px-3 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2" :class="activeTool === tool[0] ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" :aria-pressed="activeTool === tool[0]" @click="$emit('tool', tool[0])">{{ tool[1] }}</button>
        <div v-if="drawing" class="relative">
            <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2" :class="activeToolIsAdvanced || showAdvanced ? 'bg-slate-200 text-slate-900' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" :aria-expanded="showAdvanced" @click="showAdvanced = !showAdvanced">Mais ferramentas</button>
            <div v-if="showAdvanced" class="absolute left-0 top-full z-20 mt-2 grid min-w-36 gap-1 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg">
                <button v-for="tool in advancedTools" :key="tool[0]" type="button" class="rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-100" :class="activeTool === tool[0] ? 'bg-slate-950 text-white hover:bg-slate-950' : ''" :aria-pressed="activeTool === tool[0]" @click="$emit('tool', tool[0]); showAdvanced = false">{{ tool[1] }}</button>
            </div>
        </div>
        <span class="mx-1 h-6 w-px bg-slate-200"></span>
        <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600" aria-label="Diminuir zoom" @click="$emit('zoom-out')">−</button>
        <span class="min-w-12 text-center text-xs font-semibold text-slate-600" role="status" aria-live="polite">{{ Math.round(zoom * 100) }}%</span>
        <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600" aria-label="Aumentar zoom" @click="$emit('zoom-in')">+</button>
        <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600" @click="$emit('reset-view')">Ajustar</button>
        <template v-if="hasWorkingPath">
            <button type="button" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white" @click="$emit('finish-path')">Concluir forma</button>
            <button type="button" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700" @click="$emit('cancel-path')">Cancelar</button>
        </template>
    </div>
</template>
