<script setup>
import { useForm } from '@inertiajs/vue3';
import StatusToggleForm from '@/components/ui/StatusToggleForm.vue';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    currentStatus: {
        type: String,
        required: true,
    },
    entityLabel: {
        type: String,
        default: 'equipamento',
    },
});

const decommissionForm = useForm({
    status: 'decommissioned',
    reason: '',
});

function decommission() {
    const confirmed = window.confirm(`Deseja descomissionar este ${props.entityLabel}?`);

    if (!confirmed) {
        return;
    }

    const reason = window.prompt('Informe o motivo do descomissionamento.');

    if (reason === null) {
        return;
    }

    const trimmedReason = reason.trim();

    if (!trimmedReason) {
        window.alert('Informe um motivo para descomissionar o equipamento.');
        return;
    }

    decommissionForm.status = 'decommissioned';
    decommissionForm.reason = trimmedReason;
    decommissionForm.patch(props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <StatusToggleForm
            v-if="currentStatus !== 'decommissioned'"
            :action="action"
            :current-status="currentStatus"
            :entity-label="entityLabel"
        />

        <button
            v-if="currentStatus !== 'decommissioned'"
            type="button"
            :disabled="decommissionForm.processing"
            class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:border-rose-300 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
            @click="decommission"
        >
            Descomissionar
        </button>

        <span
            v-else
            class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
        >
            Descomissionado
        </span>
    </div>
</template>
