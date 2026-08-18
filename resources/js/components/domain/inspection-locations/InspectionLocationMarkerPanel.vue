<script setup>
defineProps({
    markers: { type: Array, default: () => [] },
    selectedPublicId: { type: String, default: null },
});

defineEmits(['select']);
</script>

<template>
    <section>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-950">Marcações salvas</h3>
                <p class="mt-1 text-xs text-slate-500">Selecione uma marcação no mapa ou nesta lista para editar.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600">{{ markers.length }}</span>
        </div>
        <div v-if="markers.length" class="max-h-72 space-y-2 overflow-y-auto pr-1">
            <button v-for="(marker, index) in markers" :key="marker.public_id" type="button" class="flex w-full items-start gap-3 rounded-xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600" :class="selectedPublicId === marker.public_id ? 'border-teal-400 bg-teal-50' : 'border-slate-200 bg-white hover:border-slate-300'" :aria-pressed="selectedPublicId === marker.public_id" @click="$emit('select', marker)">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">{{ index + 1 }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-slate-950">{{ marker.defect_code || 'Sem avaria vinculada' }}</span>
                    <span class="mt-1 block truncate text-xs text-slate-600">{{ marker.defect_title || marker.label || 'Sem título' }}</span>
                    <span class="mt-1 block text-[11px] text-slate-500">{{ marker.geometry?.shapes?.length || 0 }} forma(s)</span>
                </span>
            </button>
        </div>
        <p v-else class="rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500">Ainda não há marcações neste mapa.</p>
    </section>
</template>
