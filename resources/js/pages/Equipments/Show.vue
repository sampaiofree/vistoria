<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import EquipmentHistory from '@/components/domain/equipments/EquipmentHistory.vue';

const props = defineProps({
    equipment: {
        type: Object,
        required: true,
    },
    client: {
        type: Object,
        required: true,
    },
    history_entries: {
        type: Array,
        default: () => [],
    },
    revision_emission_types: {
        type: Array,
        default: () => [],
    },
    can: {
        type: Object,
        required: true,
    },
    index_url: {
        type: String,
        required: true,
    },
    edit_url: {
        type: String,
        required: true,
    },
    revision_store_url: {
        type: String,
        required: true,
    },
});

const assetFields = {
    "maintenance_plan_code": "Plano de manutenção",
    "maintenance_item_code": "Item manutenção",
    "defect_code_prefix": "Prefixo de avaria",
    "tag": "Campo de ordenação (TAG)",
    "description": "Descrição item de manutenção",
    "installation_location": "Local de instalação",
    "area_code": "Area(usina)",
    "area_name": "Area.nome",
    "subarea_code": "Sub-area",
    "subarea_name": "sub-area.nome",
    "name": "Denominação do loc.instalação",
    "task_list_group": "GrpLisTar.",
    "task_list_group_counter": "Numerador de grupos",
    "abc_code": "Código ABC"
};

const subtitle = computed(() => `${props.client.name} · Item manutenção ${props.equipment.maintenance_item_code || 'Não informado'} · TAG ${props.equipment.tag}`);
</script>

<template>
    <AppLayout
        :title="equipment.name"
        :subtitle="subtitle"
        wide
    >
        <template #actions>
            <StatusBadge :status="equipment.status" />

            <Link
                v-if="can.update"
                :href="edit_url"
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Editar
            </Link>

            <Link
                :href="index_url"
                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
            >
                Voltar aos equipamentos
            </Link>
        </template>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Dados do ativo</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(label, field) in assetFields" :key="field">
                        <dt class="text-sm text-slate-500">{{ label }}</dt>
                        <dd class="mt-1 break-words whitespace-pre-line text-sm font-medium text-slate-900">{{ equipment[field] || '—' }}</dd>
                    </div>
                </dl>
            </section>
            <EquipmentHistory
                :entries="history_entries"
                :emission-types="revision_emission_types"
                :can-manage="can.manage_revisions"
                :store-url="revision_store_url"
            />
        </div>
    </AppLayout>
</template>
