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
    revision_users: {
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

const subtitle = computed(() => `${props.client.name} · TAG ${props.equipment.tag}`);
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
            <EquipmentHistory
                :entries="history_entries"
                :emission-types="revision_emission_types"
                :users="revision_users"
                :can-manage="can.manage_revisions"
                :store-url="revision_store_url"
            />
        </div>
    </AppLayout>
</template>
