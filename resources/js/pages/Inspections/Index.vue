<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import InspectionStatusBadge from '@/components/domain/inspections/InspectionStatusBadge.vue';

const props = defineProps({
    inspections: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    options: {
        type: Object,
        required: true,
    },
    capabilities: {
        type: Object,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    scheduled_from: props.filters.scheduled_from ?? '',
    scheduled_to: props.filters.scheduled_to ?? '',
});

const hasFilters = computed(() => Object.values(filterForm).some((value) => value !== ''));

watch(() => props.filters, (filters) => {
    Object.assign(filterForm, {
        search: filters.search ?? '',
        status: filters.status ?? '',
        scheduled_from: filters.scheduled_from ?? '',
        scheduled_to: filters.scheduled_to ?? '',
    });
}, { deep: true });

function applyFilters() {
    const filters = Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== ''));

    router.get('/inspections', filters, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    Object.assign(filterForm, {
        search: '',
        status: '',
        scheduled_from: '',
        scheduled_to: '',
    });

    router.get('/inspections', {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout
        title="Inspeções"
        subtitle="Planejamento, execução e liberação de inspeções."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(15rem,1fr)_13rem_10rem_10rem_auto]" @submit.prevent="applyFilters">
                    <label class="space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Buscar</span>
                        <input v-model="filterForm.search" type="search" placeholder="Número, OS, item, TAG ou equipamento" class="min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</span>
                        <select v-model="filterForm.status" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100">
                            <option value="">Todos</option>
                            <option v-for="status in options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Planejada de</span>
                        <input v-model="filterForm.scheduled_from" type="date" class="min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100">
                    </label>
                    <label class="space-y-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Planejada até</span>
                        <input v-model="filterForm.scheduled_to" type="date" class="min-h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100">
                    </label>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">Filtrar</button>
                        <button v-if="hasFilters" type="button" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="clearFilters">Limpar</button>
                    </div>
                </form>
                <Link v-if="capabilities.create" :href="create_url" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">
                    Nova inspeção
                </Link>
            </div>

            <p v-if="!capabilities.create" class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                Somente usuários com papel Planejador podem criar inspeções. Solicite a definição do papel ao administrador.
            </p>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Número</th>
                            <th class="px-5 py-3">Equipamento</th>
                            <th class="px-5 py-3">Planejador</th>
                            <th class="px-5 py-3">Inspetor</th>
                            <th class="px-5 py-3">Revisor</th>
                            <th class="px-5 py-3">Liberador</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="inspection in inspections.data" :key="inspection.public_id">
                            <td class="px-5 py-4 font-semibold text-slate-900">{{ inspection.number }}</td>
                            <td class="px-5 py-4">
                                <strong>{{ inspection.equipment.tag }}</strong>
                                <div class="text-slate-500">{{ inspection.equipment.name }}</div>
                            </td>
                            <td class="px-5 py-4 text-slate-700">{{ inspection.stage_responsibles.planner || '—' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ inspection.stage_responsibles.inspector || '—' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ inspection.stage_responsibles.reviewer || '—' }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ inspection.stage_responsibles.releaser || '—' }}</td>
                            <td class="px-5 py-4">
                                <InspectionStatusBadge :status="inspection.status" />
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ inspection.status_milestone.label }}: <span class="font-medium text-slate-700">{{ inspection.status_milestone.value || '—' }}</span>
                                </p>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="inspection.show_url" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-teal-700 px-3 text-sm font-semibold text-teal-700 transition hover:bg-teal-50">
                                        Ver
                                    </Link>
                                    <Link
                                        v-if="inspection.edit_url"
                                        :href="inspection.edit_url"
                                        class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="inspections.data.length === 0">
                            <td colspan="8" class="px-5 py-10 text-center text-slate-500">
                                Nenhuma inspeção encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4">
                <Pagination :links="inspections.links" />
            </div>
        </section>
    </AppLayout>
</template>
