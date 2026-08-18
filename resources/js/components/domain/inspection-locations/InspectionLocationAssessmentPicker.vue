<script setup>
defineProps({ assessments: { type: Array, default: () => [] }, selectedId: { type: Number, default: null } });
defineEmits(['select']);

function photoLabel(photo) {
    const number = Number(photo?.report_number);

    return Number.isInteger(number) && number > 0
        ? `Foto ${number}`
        : 'Ainda sem número';
}
</script>

<template>
    <section>
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-slate-950">Qual avaria esta marcação representa?</h3>
            <p class="mt-1 text-xs leading-5 text-slate-500">Selecione uma avaria para concluir a marcação.</p>
        </div>
        <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
            <button v-for="assessment in assessments" :key="assessment.id" type="button" class="w-full rounded-xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600" :class="selectedId === assessment.id ? 'border-teal-500 bg-teal-50 ring-2 ring-teal-100' : 'border-slate-200 bg-white hover:border-slate-300'" :aria-pressed="selectedId === assessment.id" @click="$emit('select', assessment)">
                <span class="text-sm font-bold text-slate-950">{{ assessment.defect_code }}</span>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ assessment.title }}</p>
                <div v-if="assessment.photos?.length" class="mt-2 flex flex-wrap gap-2">
                    <span v-for="photo in assessment.photos" :key="photo.public_id" class="relative h-12 w-16 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                        <img v-if="photo.thumbnail_url" :src="photo.thumbnail_url" :alt="photo.caption || photoLabel(photo)" class="h-full w-full object-cover">
                        <span class="absolute inset-x-0 bottom-0 bg-slate-950/75 px-1 py-0.5 text-center text-[9px] font-bold text-white">{{ photoLabel(photo) }}</span>
                    </span>
                </div>
            </button>
            <p v-if="!assessments.length" class="rounded-xl bg-slate-50 p-4 text-sm leading-5 text-slate-500">Nenhuma avaliação publicada está disponível para este mapa.</p>
        </div>
    </section>
</template>
