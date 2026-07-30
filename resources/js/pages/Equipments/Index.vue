<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';

const props = defineProps({
    equipments: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    clients: {
        type: Array,
        required: true,
    },
    units: {
        type: Array,
        required: true,
    },
    areas: {
        type: Array,
        required: true,
    },
    subareas: {
        type: Array,
        required: true,
    },
    status_options: {
        type: Array,
        required: true,
    },
    can: {
        type: Object,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
});

const form = useForm({
    search: props.filters.search ?? '',
    client: props.filters.client ?? '',
    unit: props.filters.unit ?? '',
    area: props.filters.area ?? '',
    subarea: props.filters.subarea ?? '',
    status: props.filters.status ?? '',
});

function submit() {
    form.get('/equipments', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout
        title="Equipamentos"
        subtitle="Cadastro permanente dos ativos técnicos da organização atual."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <form class="grid flex-1 gap-3 lg:grid-cols-6" @submit.prevent="submit">
                    <label class="lg:col-span-2">
                        <span class="sr-only">Buscar equipamento</span>
                        <input
                            v-model="form.search"
                            type="search"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            placeholder="Buscar por TAG, nome, fabricante, modelo, série ou código"
                        >
                    </label>

                    <select v-model="form.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        <option v-for="option in status_options" :key="option.value || 'all'" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <select v-model="form.client" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        <option value="">Cliente</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">
                            {{ client.name }}
                        </option>
                    </select>

                    <select v-model="form.unit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        <option value="">Unidade</option>
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>

                    <select v-model="form.area" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        <option value="">Área</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">
                            {{ area.name }}
                        </option>
                    </select>

                    <select v-model="form.subarea" class="rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100">
                        <option value="">Subárea</option>
                        <option v-for="subarea in subareas" :key="subarea.id" :value="subarea.id">
                            {{ subarea.name }}
                        </option>
                    </select>

                    <button
                        type="submit"
                        class="lg:col-span-6 inline-flex items-center justify-center rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                    >
                        Filtrar
                    </button>
                </form>

                <Link
                    v-if="can.create"
                    :href="create_url"
                    class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                >
                    Novo equipamento
                </Link>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="text-sm font-semibold text-slate-900">
                    Equipamentos
                </div>
                <div class="text-sm text-slate-500">
                    {{ equipments.total }} registro(s)
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">TAG</th>
                            <th class="px-5 py-3">Equipamento</th>
                            <th class="px-5 py-3">Localização</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="equipment in equipments.data" :key="equipment.public_id">
                            <td class="px-5 py-4 font-semibold text-slate-900">
                                {{ equipment.tag }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900">{{ equipment.name }}</div>
                                <div class="text-sm text-slate-500">
                                    <span v-if="equipment.manufacturer">{{ equipment.manufacturer }}</span>
                                    <span v-if="equipment.manufacturer && equipment.model"> · </span>
                                    <span v-if="equipment.model">{{ equipment.model }}</span>
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ equipment.serial_number ?? '—' }}
                                </div>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-600">
                                <div class="font-medium text-slate-900">{{ equipment.client.name }}</div>
                                <div>{{ equipment.unit.name }}</div>
                                <div>{{ equipment.area.name }}</div>
                                <div v-if="equipment.subarea">
                                    {{ equipment.subarea.name }}
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <StatusBadge :status="equipment.status" />
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="equipment.show_url"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                    >
                                        Ver
                                    </Link>
                                    <Link
                                        v-if="equipment.can_update"
                                        :href="equipment.edit_url"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
                                    >
                                        Editar
                                    </Link>
                                    <StatusToggleForm
                                        v-if="equipment.can_change_status && equipment.status !== 'decommissioned'"
                                        :action="equipment.status_url"
                                        :current-status="equipment.status"
                                        entity-label="equipamento"
                                    />
                                    <span
                                        v-else-if="equipment.status === 'decommissioned'"
                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-amber-800"
                                    >
                                        Sem ação
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="equipments.data.length === 0">
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                Nenhum equipamento encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-4">
                <Pagination :links="equipments.links" />
            </div>
        </section>
    </AppLayout>
</template>
