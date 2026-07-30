<script setup>
import { Link, useForm } from '@inertiajs/vue3';
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

const form = useForm({
    search: props.filters.search ?? props.filters.number ?? '',
    number: props.filters.number ?? props.filters.search ?? '',
    client: props.filters.client ?? '',
    unit: props.filters.unit ?? '',
    equipment: props.filters.equipment ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? props.filters.inspection_type ?? '',
    inspection_type: props.filters.inspection_type ?? props.filters.type ?? '',
    responsible: props.filters.responsible ?? '',
    responsibility: props.filters.responsibility ?? '',
    scheduled_from: props.filters.scheduled_from ?? props.filters.from ?? '',
    scheduled_to: props.filters.scheduled_to ?? props.filters.to ?? '',
    inspected_from: props.filters.inspected_from ?? '',
    inspected_to: props.filters.inspected_to ?? '',
    from: props.filters.from ?? props.filters.scheduled_from ?? '',
    to: props.filters.to ?? props.filters.scheduled_to ?? '',
});

function submit() {
    form.number = form.search;
    form.inspection_type = form.type;
    form.from = form.scheduled_from;
    form.to = form.scheduled_to;
    form.get('/inspections', {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function clear() {
    form.search = '';
    form.number = '';
    form.client = '';
    form.unit = '';
    form.equipment = '';
    form.status = '';
    form.type = '';
    form.inspection_type = '';
    form.responsible = '';
    form.responsibility = '';
    form.scheduled_from = '';
    form.scheduled_to = '';
    form.inspected_from = '';
    form.inspected_to = '';
    form.from = '';
    form.to = '';
    submit();
}
</script>

<template>
    <AppLayout
        title="Inspeções"
        subtitle="Planejamento, execução e liberação de inspeções."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="submit">
                <label class="text-sm font-medium text-slate-700">
                    Buscar
                    <input
                        v-model="form.search"
                        placeholder="Número, TAG, cliente ou responsável"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Cliente
                    <select v-model="form.client" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todos</option>
                        <option v-for="item in options.clients" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Unidade
                    <select v-model="form.unit" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todas</option>
                        <option v-for="item in options.units" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Equipamento
                    <select v-model="form.equipment" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todos</option>
                        <option v-for="item in options.equipment" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Status
                    <select v-model="form.status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todos</option>
                        <option v-for="item in options.statuses" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Tipo
                    <select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todos</option>
                        <option v-for="item in options.types" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Responsável
                    <select v-model="form.responsible" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                        <option value="">Todos</option>
                        <option v-for="item in options.responsibles" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Planejada de
                    <input v-model="form.scheduled_from" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Planejada até
                    <input v-model="form.scheduled_to" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Inspecionada de
                    <input v-model="form.inspected_from" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>

                <label class="text-sm font-medium text-slate-700">
                    Inspecionada até
                    <input v-model="form.inspected_to" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>

                <div class="flex items-end gap-2 xl:col-span-4">
                    <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">
                        Filtrar
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
                        @click="clear"
                    >
                        Limpar
                    </button>
                </div>
            </form>

            <div v-if="capabilities.create" class="mt-4 flex justify-end">
                <Link :href="create_url" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">
                    Nova inspeção
                </Link>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Número</th>
                            <th class="px-5 py-3">Equipamento</th>
                            <th class="px-5 py-3">Cliente / Unidade</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Responsável principal</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Planejada</th>
                            <th class="px-5 py-3">Inspecionada</th>
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
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900">{{ inspection.equipment.client?.name || '—' }}</div>
                                <div class="text-slate-500">{{ inspection.equipment.unit?.name || '—' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                {{ inspection.type === 'reinspection' ? 'Reinspeção' : 'Inicial' }}
                            </td>
                            <td class="px-5 py-4 text-slate-700">
                                {{ inspection.primary_responsible?.name || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <InspectionStatusBadge :status="inspection.status" />
                            </td>
                            <td class="px-5 py-4">{{ inspection.scheduled_at || '—' }}</td>
                            <td class="px-5 py-4">{{ inspection.inspected_at || '—' }}</td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link :href="inspection.show_url" class="font-semibold text-teal-700 hover:text-teal-800">
                                        Ver
                                    </Link>
                                    <Link
                                        v-if="inspection.edit_url"
                                        :href="inspection.edit_url"
                                        class="font-semibold text-slate-600 hover:text-slate-900"
                                    >
                                        Editar
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="inspections.data.length === 0">
                            <td colspan="9" class="px-5 py-10 text-center text-slate-500">
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
