<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    inspection: { type: Object, required: true },
    equipmentOptions: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    selectedInspectorId: { type: [Number, String], default: null },
    submitLabel: { type: String, default: 'Salvar alterações' },
});

const form = useForm({
    equipment_id: props.inspection.equipment_id ?? '',
    inspector_id: props.selectedInspectorId ?? '',
    service_order: props.inspection.service_order ?? '',
    planned_start_on: props.inspection.planned_start_on_input ?? '',
    planned_end_on: props.inspection.planned_end_on_input ?? '',
});

function submit() {
    form.put(props.action, { preserveScroll: true });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 md:grid-cols-3">
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Número</p><p class="mt-1 font-semibold text-slate-900">{{ inspection.number }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Tipo atual</p><p class="mt-1 font-semibold text-slate-900">{{ inspection.inspection_type_label }}</p></div>
                <div><p class="text-xs uppercase tracking-wide text-slate-500">Status</p><p class="mt-1 font-semibold text-slate-900">{{ inspection.status_label }}</p></div>
            </div>
            <p class="mt-4 text-sm text-slate-500">Alterar o equipamento recalcula automaticamente o tipo, a inspeção anterior e o snapshot técnico.</p>
        </section>

        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Dados de planejamento</h3>
                <p class="text-sm text-slate-500">Somente o Planejador vinculado pode editar estes dados enquanto a inspeção estiver planejada.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Equipamento</span>
                    <select v-model="form.equipment_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="" disabled>Selecione o equipamento</option><option v-for="equipment in equipmentOptions" :key="equipment.value" :value="equipment.value">{{ equipment.label }}</option></select>
                    <span v-if="form.errors.equipment_id" class="block text-xs text-rose-600">{{ form.errors.equipment_id }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Inspetor</span>
                    <select v-model="form.inspector_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="" disabled>Selecione o Inspetor</option><option v-for="inspector in inspectors" :key="inspector.id" :value="inspector.id">{{ inspector.name }}</option></select>
                    <span v-if="form.errors.inspector_id" class="block text-xs text-rose-600">{{ form.errors.inspector_id }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                    <span>Ordem de serviço</span>
                    <input v-model="form.service_order" type="text" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <span v-if="form.errors.service_order" class="block text-xs text-rose-600">{{ form.errors.service_order }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Data inicial planejada</span>
                    <input v-model="form.planned_start_on" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <span v-if="form.errors.planned_start_on" class="block text-xs text-rose-600">{{ form.errors.planned_start_on }}</span>
                </label>
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Prazo final planejado</span>
                    <input v-model="form.planned_end_on" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <span v-if="form.errors.planned_end_on" class="block text-xs text-rose-600">{{ form.errors.planned_end_on }}</span>
                </label>
            </div>
        </section>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
            <Link :href="cancelUrl" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</Link>
            <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ submitLabel }}</button>
        </div>
    </form>
</template>
