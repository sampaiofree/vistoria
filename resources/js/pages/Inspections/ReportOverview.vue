<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionOverviewBlockCard from '@/components/domain/inspections/InspectionOverviewBlockCard.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';

const props = defineProps({
    inspection: { type: Object, required: true },
    overview: { type: Object, required: true },
    tabs: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'report_overview' },
    capabilities: { type: Object, default: () => ({}) },
});

const equipmentLabel = computed(() => `${props.inspection.equipment.name} ${props.inspection.equipment.tag}`.trim());
const readyPhotos = computed(() => props.overview.blocks
    .flatMap((block) => block.photos)
    .filter((slot) => slot.photo?.status === 'ready').length);
</script>

<template>
    <AppLayout
        title="Vista geral"
        :subtitle="`${inspection.number || 'Inspeção'} · ${inspection.equipment.tag} — ${inspection.equipment.name}`"
        wide
    >
        <template #actions>
            <InspectionStatusBadge :status="inspection.status" />
            <Link :href="inspection.equipment.show_url" class="rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Equipamento</Link>
        </template>

        <div class="lg:hidden">
            <InspectionTabs :tabs="tabs" :active="active_tab" />
        </div>

        <section class="mt-6 rounded-md border border-slate-200 bg-white p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Documentação fotográfica inicial</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                        Estas quatro fotografias aparecem antes dos mapas no relatório e não precisam estar relacionadas a uma marcação.
                    </p>
                </div>
                <span class="self-start rounded px-2.5 py-1.5 text-xs font-semibold" :class="overview.complete ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                    {{ readyPhotos }}/4 fotos prontas
                </span>
            </div>
            <p v-if="!capabilities.edit" class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Esta seção está disponível somente para leitura.
            </p>
        </section>

        <div class="mt-5 space-y-5">
            <InspectionOverviewBlockCard
                v-for="block in overview.blocks"
                :key="block.position"
                :block="block"
                :equipment-label="equipmentLabel"
            />
        </div>
    </AppLayout>
</template>
