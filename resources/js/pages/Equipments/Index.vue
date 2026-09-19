<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
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
    can: {
        type: Object,
        required: true,
    },
    create_url: {
        type: String,
        required: true,
    },
    import_url: {
        type: String,
        required: true,
    },
    index_url: {
        type: String,
        required: true,
    },
});

const form = useForm({
    search: props.filters.search ?? '',
});
let searchTimer = null;

function search() {
    form.get(props.index_url, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(() => form.search, () => {
    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }

    searchTimer = window.setTimeout(search, 300);
});

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        window.clearTimeout(searchTimer);
    }
});
</script>

<template>
    <AppLayout
        title="Equipamentos"
        subtitle="Cadastro permanente dos ativos técnicos da organização atual."
    >
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex-1">
                    <label class="block max-w-xl">
                        <span class="sr-only">Buscar equipamento</span>
                        <input
                            v-model="form.search"
                            type="search"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            placeholder="Buscar por item, TAG, prefixo, descrição, área ou subárea"
                        >
                    </label>
                </div>

                <div v-if="can.create" class="flex flex-wrap gap-2">
                    <Link
                        :href="import_url"
                        class="inline-flex items-center justify-center rounded-lg border border-teal-600 bg-white px-4 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-50"
                    >
                        Importar CSV
                    </Link>
                    <Link
                        :href="create_url"
                        class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700"
                    >
                        Novo equipamento
                    </Link>
                </div>
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
                            <th class="px-5 py-3">Item manutenção</th>
                            <th class="px-5 py-3">TAG</th>
                            <th class="px-5 py-3">Prefixo de avaria</th>
                            <th class="px-5 py-3">Descrição</th>
                            <th class="px-5 py-3">Área</th>
                            <th class="px-5 py-3">Subárea</th>
                            <th class="px-5 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="equipment in equipments.data" :key="equipment.public_id">
                            <td class="px-5 py-4 font-semibold text-slate-900">
                                {{ equipment.maintenance_item_code || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                {{ equipment.tag || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                {{ equipment.defect_code_prefix || '—' }}
                            </td>
                            <td class="max-w-xs px-5 py-4">
                                <span class="line-clamp-2">{{ equipment.description || '—' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                {{ equipment.area_name || '—' }}
                            </td>
                            <td class="px-5 py-4">
                                {{ equipment.subarea_name || '—' }}
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
                            <td colspan="7" class="px-5 py-10 text-center text-sm text-slate-500">
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
