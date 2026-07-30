<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    cancelUrl: {
        type: String,
        required: true,
    },
    equipment: {
        type: Array,
        default: () => [],
    },
    releasedInspections: {
        type: Array,
        default: () => [],
    },
    inspection: {
        type: Object,
        default: null,
    },
    inspectionTypes: {
        type: Array,
        default: () => [],
    },
    mode: {
        type: String,
        default: 'create',
    },
    submitLabel: {
        type: String,
        default: '',
    },
});

const isEditing = computed(() => props.mode === 'edit' || props.inspection !== null);

const defaultInspectionTypes = [
    { value: 'initial', label: 'Inspeção inicial' },
    { value: 'reinspection', label: 'Reinspeção' },
];

const form = useForm({
    equipment_id: props.inspection?.equipment_id ?? '',
    inspection_type: props.inspection?.inspection_type ?? 'initial',
    previous_inspection_id: props.inspection?.previous_inspection_id ?? '',
    service_order: props.inspection?.service_order ?? '',
    external_report_number: props.inspection?.external_report_number ?? '',
    procedure_number: props.inspection?.procedure_number ?? '',
    atmospheric_classification: props.inspection?.atmospheric_classification ?? '',
    scheduled_for: props.inspection?.scheduled_for_input ?? '',
    general_notes: props.inspection?.general_notes ?? '',
});

const inspectionTypes = computed(() => props.inspectionTypes.length > 0 ? props.inspectionTypes : defaultInspectionTypes);

const previousOptions = computed(() => props.releasedInspections.filter((inspection) =>
    inspection.status === 'released' && String(inspection.equipment_id) === String(form.equipment_id),
));

const previousInspection = computed(() => {
    if (! isEditing.value) {
        return previousOptions.value.find((inspection) => String(inspection.id) === String(form.previous_inspection_id)) ?? null;
    }

    return props.inspection?.previous_inspection ?? null;
});

watch(
    () => [form.equipment_id, form.inspection_type],
    () => {
        if (! isEditing.value) {
            form.previous_inspection_id = '';
        }
    },
);

function submit() {
    form[isEditing.value ? 'put' : 'post'](props.action, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section v-if="isEditing" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Número</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ inspection.number }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Equipamento</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ inspection.equipment.tag }} — {{ inspection.equipment.name }}</p>
                    <p v-if="inspection.equipment.client" class="text-sm text-slate-500">{{ inspection.equipment.client.name }}</p>
                    <p v-if="inspection.equipment.unit" class="text-sm text-slate-500">{{ inspection.equipment.unit.name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Tipo</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ inspection.inspection_type_label }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ inspection.status_label }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Inspeção anterior</p>
                    <Link
                        v-if="previousInspection"
                        :href="previousInspection.show_url"
                        class="mt-1 inline-flex font-semibold text-teal-700 hover:text-teal-800"
                    >
                        {{ previousInspection.number }}
                    </Link>
                    <p v-else class="mt-1 font-semibold text-slate-900">—</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Planejada para</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ inspection.scheduled_at || '—' }}</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-slate-500">
                Somente os campos de planejamento podem ser alterados nesta etapa.
            </p>
        </section>

        <section v-else class="grid gap-5 md:grid-cols-2">
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>Equipamento</span>
                <select
                    v-model="form.equipment_id"
                    required
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="" disabled>Selecione o equipamento</option>
                    <option v-for="item in equipment" :key="item.id" :value="item.id">
                        {{ item.tag }} — {{ item.name }}
                    </option>
                </select>
                <span v-if="form.errors.equipment_id" class="block text-xs text-rose-600">{{ form.errors.equipment_id }}</span>
            </label>

            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>Tipo</span>
                <select v-model="form.inspection_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option v-for="item in inspectionTypes" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </label>

            <label v-if="form.inspection_type === 'reinspection'" class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                <span>Inspeção anterior liberada</span>
                <select
                    v-model="form.previous_inspection_id"
                    required
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"
                >
                    <option value="" disabled>Selecione uma inspeção do mesmo equipamento</option>
                    <option v-for="inspection in previousOptions" :key="inspection.id" :value="inspection.id">
                        {{ inspection.number }} · liberada em {{ inspection.released_at }}
                    </option>
                </select>
                <span
                    v-if="previousOptions.length === 0"
                    class="block text-xs font-normal text-slate-500"
                >
                    Não há inspeções liberadas para este equipamento.
                </span>
                <span v-if="form.errors.previous_inspection_id" class="block text-xs text-rose-600">{{ form.errors.previous_inspection_id }}</span>
            </label>
        </section>

        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Dados de planejamento</h3>
                <p class="text-sm text-slate-500">Esses campos continuam editáveis enquanto a inspeção estiver planejada.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Ordem de serviço</span>
                    <input
                        v-model="form.service_order"
                        type="text"
                        maxlength="100"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <span v-if="form.errors.service_order" class="block text-xs text-rose-600">{{ form.errors.service_order }}</span>
                </label>

                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Número do relatório externo</span>
                    <input
                        v-model="form.external_report_number"
                        type="text"
                        maxlength="150"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <span v-if="form.errors.external_report_number" class="block text-xs text-rose-600">{{ form.errors.external_report_number }}</span>
                </label>

                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Número do procedimento</span>
                    <input
                        v-model="form.procedure_number"
                        type="text"
                        maxlength="150"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <span v-if="form.errors.procedure_number" class="block text-xs text-rose-600">{{ form.errors.procedure_number }}</span>
                </label>

                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Classificação atmosférica</span>
                    <input
                        v-model="form.atmospheric_classification"
                        type="text"
                        maxlength="50"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <span v-if="form.errors.atmospheric_classification" class="block text-xs text-rose-600">{{ form.errors.atmospheric_classification }}</span>
                </label>

                <label class="space-y-1.5 text-sm font-medium text-slate-700">
                    <span>Data planejada</span>
                    <input
                        v-model="form.scheduled_for"
                        type="date"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <span v-if="form.errors.scheduled_for" class="block text-xs text-rose-600">{{ form.errors.scheduled_for }}</span>
                </label>

                <label class="space-y-1.5 text-sm font-medium text-slate-700 md:col-span-2">
                    <span>Observações gerais</span>
                    <textarea
                        v-model="form.general_notes"
                        rows="4"
                        maxlength="10000"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    ></textarea>
                    <span v-if="form.errors.general_notes" class="block text-xs text-rose-600">{{ form.errors.general_notes }}</span>
                </label>
            </div>
        </section>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
            <Link
                :href="cancelUrl"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700"
            >
                Cancelar
            </Link>
            <button
                :disabled="form.processing"
                class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
                {{ submitLabel || (isEditing ? 'Salvar alterações' : 'Criar inspeção') }}
            </button>
        </div>
    </form>
</template>
