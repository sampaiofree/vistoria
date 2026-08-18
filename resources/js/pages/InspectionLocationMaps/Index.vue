<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import AssessmentProgress from '@/components/domain/view-first/AssessmentProgress.vue';
import InspectionTabs from '@/components/domain/view-first/InspectionTabs.vue';
import InspectionLocationMapCard from '@/components/domain/inspection-locations/InspectionLocationMapCard.vue';

const props = defineProps({
    inspection: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    maps: { type: Array, default: () => [] },
    unlocated_assessments: { type: Array, default: () => [] },
    unresolved_markers: { type: Array, default: () => [] },
    coverage: { type: Object, default: () => ({}) },
    create_url: { type: String, required: true },
    can_create: { type: Boolean, default: false },
    tabs: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'locations' },
    copy_previous: { type: Object, default: null },
    progress: { type: Object, default: () => ({ completed: 0, total: 0, percentage: 0 }) },
});

const activeView = ref('blocks');
const activeStatus = ref('all');
const visibleMaps = computed(() => props.maps.filter((map) => activeStatus.value === 'all' || map.processing_status === activeStatus.value));

onMounted(() => {
    const storedView = window.localStorage.getItem('vistoria.location-maps.view-mode');
    activeView.value = ['blocks', 'list'].includes(storedView) ? storedView : 'blocks';
});

function setView(view) {
    activeView.value = view;
    window.localStorage.setItem('vistoria.location-maps.view-mode', view);
}

function copyPrevious() {
    if (props.copy_previous && window.confirm('Copiar ' + props.copy_previous.map_count + ' mapa(s) da inspeção anterior? As fotografias não serão copiadas.')) {
        router.post(props.copy_previous.url);
    }
}
</script>

<template>
    <AppLayout title="Localização" :subtitle="(inspection.number || 'Inspeção') + ' · ' + inspection.equipment.tag + ' — ' + inspection.equipment.name" wide>
        <template #actions>
            <InspectionStatusBadge :status="inspection.status" />
            <div class="hidden min-w-44 sm:block">
                <AssessmentProgress :progress="progress" label="Avaliações" />
            </div>
            <button v-if="copy_previous?.map_count" type="button" class="rounded-xl border border-teal-200 bg-white px-3.5 py-2 text-sm font-semibold text-teal-800" @click="copyPrevious">Copiar mapas</button>
        </template>
        <div class="lg:hidden">
            <InspectionTabs :tabs="tabs" :active="active_tab" />
        </div>

        <section v-if="coverage.enabled_categories" class="mt-6 rounded-2xl border p-4" :class="coverage.is_complete ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold" :class="coverage.is_complete ? 'text-emerald-900' : 'text-amber-900'">{{ coverage.is_complete ? 'Cobertura de localização completa' : 'Cobertura de localização pendente' }}</p>
                    <p class="mt-1 text-xs" :class="coverage.is_complete ? 'text-emerald-700' : 'text-amber-700'">{{ coverage.located_assessments }}/{{ coverage.required_assessments }} avaliações localizadas · {{ coverage.ready_maps }}/{{ coverage.map_count }} mapas prontos</p>
                </div>
                <span v-if="!coverage.is_complete" class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-amber-800">{{ coverage.missing_markers }} marcação(ões) pendente(s)</span>
            </div>
        </section>

        <div class="mt-6 space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <button v-for="status in [{ key: 'all', label: 'Todos' }, { key: 'ready', label: 'Disponíveis' }, { key: 'processing', label: 'Processando' }, { key: 'failed', label: 'Com falha' }]" :key="status.key" type="button" class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="activeStatus === status.key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" @click="activeStatus = status.key">{{ status.label }}</button>
                    </div>
                    <div class="flex w-fit rounded-xl bg-slate-100 p-1">
                        <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="activeView === 'blocks' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="setView('blocks')">Blocos</button>
                        <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="activeView === 'list' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'" @click="setView('list')">Lista</button>
                    </div>
                </div>
            </section>

            <section v-if="visibleMaps.length" :class="activeView === 'blocks' ? 'grid gap-4 md:grid-cols-2 xl:grid-cols-3' : 'space-y-3'">
                <InspectionLocationMapCard v-for="map in visibleMaps" :id="'map-' + map.public_id" :key="map.id" :map="map" :variant="activeView === 'list' ? 'list' : 'card'" class="scroll-mt-24" />
            </section>
            <section v-else class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <h2 class="font-semibold text-slate-950">Nenhum mapa encontrado</h2>
                <p class="mt-2 text-sm text-slate-500">Use “+ Novo mapa” no menu lateral para cadastrar uma localização.</p>
            </section>

            <section v-if="unlocated_assessments.length" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                <h2 class="text-sm font-semibold text-amber-900">Avaliações ainda sem marcação ({{ unlocated_assessments.length }})</h2>
                <div class="mt-3 grid gap-2 md:grid-cols-2">
                    <Link v-for="assessment in unlocated_assessments" :key="assessment.id" :href="assessment.show_url" class="rounded-xl bg-white p-3 text-sm hover:ring-2 hover:ring-amber-200">
                        <strong class="text-slate-950">{{ assessment.category?.code ? assessment.category.code + ' · ' : '' }}{{ assessment.defect_code }}</strong>
                        <span class="ml-2 text-slate-600">{{ assessment.title }}</span>
                        <p class="mt-1 text-xs text-slate-500">{{ assessment.location_description || 'Localização textual não informada' }}</p>
                    </Link>
                </div>
            </section>
            <section v-if="unresolved_markers.length" class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-sm font-semibold text-rose-900">{{ unresolved_markers.length }} marcação(ões) copiadas precisam ser associadas a uma avaliação atual.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link v-for="marker in unresolved_markers" :key="marker.public_id" :href="marker.edit_url" class="rounded-lg bg-white px-3 py-2 text-xs font-semibold text-rose-800">{{ marker.category?.code ? marker.category.code + ' · ' : '' }}{{ marker.label || marker.map_title }}</Link>
                </div>
            </section>

            <section v-if="!categories.length" class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <h2 class="font-semibold text-slate-950">Nenhuma categoria disponível</h2>
                <p class="mt-2 text-sm text-slate-500">Cadastre uma categoria de avaria antes de criar mapas.</p>
            </section>
        </div>
    </AppLayout>
</template>
