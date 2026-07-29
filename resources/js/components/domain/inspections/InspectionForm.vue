<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: { type: String, required: true },
    equipment: { type: Array, default: () => [] },
    releasedInspections: { type: Array, default: () => [] },
    cancelUrl: { type: String, required: true },
});

const form = useForm({ equipment_id: '', inspection_type: 'initial', previous_inspection_id: '', scheduled_at: '' });
const previousOptions = computed(() => props.releasedInspections.filter((inspection) =>
    inspection.status === 'released' && String(inspection.equipment_id) === String(form.equipment_id),
));

watch(() => [form.equipment_id, form.inspection_type], () => { form.previous_inspection_id = ''; });

function submit() { form.post(props.action); }
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>Equipamento</span>
                <select v-model="form.equipment_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="" disabled>Selecione o equipamento</option>
                    <option v-for="item in equipment" :key="item.id" :value="item.id">{{ item.tag }} — {{ item.name }}</option>
                </select>
                <span v-if="form.errors.equipment_id" class="block text-xs text-rose-600">{{ form.errors.equipment_id }}</span>
            </label>
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>Tipo</span>
                <select v-model="form.inspection_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="initial">Inspeção inicial</option>
                    <option value="reinspection">Reinspeção</option>
                </select>
            </label>
            <label v-if="form.inspection_type === 'reinspection'" class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                <span>Inspeção anterior liberada</span>
                <select v-model="form.previous_inspection_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="" disabled>Selecione uma inspeção do mesmo equipamento</option>
                    <option v-for="inspection in previousOptions" :key="inspection.id" :value="inspection.id">
                        {{ inspection.number }} · liberada em {{ inspection.released_at }}
                    </option>
                </select>
                <span v-if="previousOptions.length === 0" class="block text-xs font-normal text-slate-500">Não há inspeções liberadas para este equipamento.</span>
                <span v-if="form.errors.previous_inspection_id" class="block text-xs text-rose-600">{{ form.errors.previous_inspection_id }}</span>
            </label>
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>Data planejada</span>
                <input v-model="form.scheduled_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
            <a :href="cancelUrl" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</a>
            <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Criar inspeção</button>
        </div>
    </form>
</template>
