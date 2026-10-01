<script setup>
import { Link } from '@inertiajs/vue3';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';
import SelfAssignmentButton from '@/components/domain/inspections/SelfAssignmentButton.vue';

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    indexUrl: {
        type: String,
        default: '',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const skeletonRows = [1, 2, 3];
</script>

<template>
    <section
        class="min-w-0 rounded-lg border border-slate-200 bg-white"
        :aria-busy="loading ? 'true' : 'false'"
    >
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Revisões disponíveis</h2>
                <p class="mt-1 text-sm text-slate-500">Inspeções aguardando a sua etapa e sem responsável atribuído.</p>
            </div>
            <Link
                v-if="indexUrl"
                :href="indexUrl"
                class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-950"
            >
                Ver todas
            </Link>
        </div>

        <div v-if="loading" class="divide-y divide-slate-100" role="status" aria-label="Carregando revisões disponíveis">
            <div v-for="row in skeletonRows" :key="row" class="animate-pulse px-5 py-4">
                <div class="h-4 w-32 rounded bg-slate-200" />
                <div class="mt-2 h-3 w-48 rounded bg-slate-100" />
            </div>
        </div>

        <ul v-else-if="rows.length > 0" class="divide-y divide-slate-100">
            <li v-for="inspection in rows" :key="inspection.public_id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <Link :href="inspection.show_url" class="font-semibold text-slate-900 transition hover:text-slate-600">
                        {{ inspection.number }}
                    </Link>
                    <div class="mt-1 text-sm text-slate-700">
                        {{ inspection.equipment.name }} · TAG {{ inspection.equipment.tag }}
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <InspectionStatusBadge :status="inspection.status" />
                        <span>Data programada: {{ inspection.schedule }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <SelfAssignmentButton :capability="inspection.self_assign" />
                    <Link :href="inspection.show_url" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Ver
                    </Link>
                </div>
            </li>
        </ul>

        <div v-else class="px-5 py-8 text-center">
            <div class="text-base font-semibold text-slate-900">Nenhuma revisão disponível.</div>
            <p class="mt-2 text-sm text-slate-500">Quando uma inspeção chegar à sua etapa sem responsável, ela aparecerá aqui.</p>
        </div>
    </section>
</template>
