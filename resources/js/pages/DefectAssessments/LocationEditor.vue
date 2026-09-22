<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import AssessmentLocationWorkspace from '@/components/domain/inspection-locations/AssessmentLocationWorkspace.vue';

const props = defineProps({
    assessment: { type: Object, required: true },
    map: { type: Object, required: true },
    location: { type: Object, default: null },
    update_url: { type: String, required: true },
    delete_url: { type: String, default: null },
});

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
                {{ assessment.category.code }} · cor da classificação
            </span>
        </div>

        <AssessmentLocationWorkspace
            :map="map"
            :location="location"
            :color="assessment.color"
            :photo-legend="assessment.photo_legend"
            :update-url="update_url"
            :delete-url="delete_url"
        />
    </AppLayout>
</template>
