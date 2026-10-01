<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import InspectionOverviewBlockCard from '@/components/domain/inspections/InspectionOverviewBlockCard.vue';
import InspectionOverviewPhotoUpload from '@/components/domain/inspections/InspectionOverviewPhotoUpload.vue';
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
const orderedPhotos = computed(() => props.overview.pages.flatMap((page) => page.photos));
const canReorder = computed(() => Boolean(props.overview.reorder_url)
    && orderedPhotos.value.every((slot) => slot.photo?.status === 'ready'));
const ordering = ref(false);
const orderError = ref('');

function move(photoId, direction) {
    if (!canReorder.value || ordering.value) return;
    const ids = orderedPhotos.value.map((slot) => slot.photo.id);
    const index = ids.indexOf(photoId);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= ids.length) return;

    [ids[index], ids[target]] = [ids[target], ids[index]];
    orderError.value = '';
    router.patch(props.overview.reorder_url, { photo_ids: ids }, {
        preserveScroll: true,
        only: ['overview', 'flash'],
        onStart: () => { ordering.value = true; },
        onError: (errors) => { orderError.value = errors.photo_ids || 'Não foi possível alterar a ordem das fotos.'; },
        onFinish: () => { ordering.value = false; },
    });
}
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
                        As fotografias aparecem antes dos mapas no relatório, em páginas de até quatro fotos. Cada página tem seus próprios textos.
                    </p>
                </div>
                <span class="self-start rounded px-2.5 py-1.5 text-xs font-semibold" :class="overview.complete ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                    {{ overview.ready_count }}/{{ overview.photo_count }} fotos prontas
                </span>
            </div>
            <p v-if="!capabilities.edit" class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Esta seção está disponível somente para leitura.
            </p>
            <InspectionOverviewPhotoUpload
                v-if="overview.append_url"
                :action="overview.append_url"
                :available="overview.max_photos - overview.photo_count"
            />
            <p v-if="capabilities.edit && !overview.append_url" class="mt-4 text-sm text-slate-500">Limite de fotografias atingido.</p>
            <p v-if="overview.photo_count % 2 !== 0" class="mt-4 text-sm font-medium text-amber-700">Adicione mais uma foto para completar o último par antes de exportar.</p>
            <p v-if="orderError" class="mt-4 text-sm font-medium text-rose-700">{{ orderError }}</p>
            <p v-if="overview.photo_count > 1 && !canReorder && capabilities.edit" class="mt-4 text-xs text-slate-500">A ordem poderá ser alterada quando todas as fotos estiverem prontas.</p>
        </section>

        <div class="mt-5 space-y-5">
            <InspectionOverviewBlockCard
                v-for="page in overview.pages"
                :key="page.number"
                :page="page"
                :equipment-label="equipmentLabel"
                :total-photos="overview.photo_count"
                :can-reorder="canReorder"
                :ordering="ordering"
                @move="move"
            />
        </div>
    </AppLayout>
</template>
