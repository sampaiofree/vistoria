<script setup>
import { computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    form: {
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
    submitLabel: {
        type: String,
        default: 'Salvar',
    },
    cancelUrl: {
        type: String,
        required: true,
    },
});

defineEmits(['submit']);

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const labelClass = 'text-sm font-medium text-slate-700';
const helpClass = 'mt-1 text-xs text-rose-600';

const filteredUnits = computed(() => props.units.filter((unit) => String(unit.client_id) === String(props.form.client_id)));
const filteredAreas = computed(() => props.areas.filter((area) => String(area.client_unit_id) === String(props.form.client_unit_id)));
const filteredSubareas = computed(() => props.subareas.filter((subarea) => String(subarea.area_id) === String(props.form.area_id)));

watch(
    () => props.form.client_id,
    () => {
        props.form.client_unit_id = '';
        props.form.area_id = '';
        props.form.subarea_id = '';
    },
);

watch(
    () => props.form.client_unit_id,
    () => {
        props.form.area_id = '';
        props.form.subarea_id = '';
    },
);

watch(
    () => props.form.area_id,
    () => {
        props.form.subarea_id = '';
    },
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span :class="labelClass">Cliente</span>
                <select v-model="form.client_id" :class="inputClass">
                    <option value="">Selecione</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">
                        {{ client.name }}
                    </option>
                </select>
                <p v-if="form.errors.client_id" :class="helpClass">{{ form.errors.client_id }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Unidade</span>
                <select v-model="form.client_unit_id" :class="inputClass" :disabled="!form.client_id">
                    <option value="">Selecione</option>
                    <option v-for="unit in filteredUnits" :key="unit.id" :value="unit.id">
                        {{ unit.name }}
                    </option>
                </select>
                <p v-if="form.errors.client_unit_id" :class="helpClass">{{ form.errors.client_unit_id }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Área</span>
                <select v-model="form.area_id" :class="inputClass" :disabled="!form.client_unit_id">
                    <option value="">Selecione</option>
                    <option v-for="area in filteredAreas" :key="area.id" :value="area.id">
                        {{ area.name }}
                    </option>
                </select>
                <p v-if="form.errors.area_id" :class="helpClass">{{ form.errors.area_id }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Subárea</span>
                <select v-model="form.subarea_id" :class="inputClass" :disabled="!form.area_id">
                    <option value="">Opcional</option>
                    <option v-for="subarea in filteredSubareas" :key="subarea.id" :value="subarea.id">
                        {{ subarea.name }}
                    </option>
                </select>
                <p v-if="form.errors.subarea_id" :class="helpClass">{{ form.errors.subarea_id }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">TAG</span>
                <input v-model="form.tag" :class="inputClass" type="text" maxlength="120" autocomplete="off">
                <p class="mt-1 text-xs text-slate-500">O valor será normalizado automaticamente.</p>
                <p v-if="form.errors.tag" :class="helpClass">{{ form.errors.tag }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Nome</span>
                <input v-model="form.name" :class="inputClass" type="text" maxlength="180" autocomplete="off">
                <p v-if="form.errors.name" :class="helpClass">{{ form.errors.name }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Descrição</span>
                <textarea v-model="form.description" :class="inputClass" rows="4" maxlength="10000"></textarea>
                <p v-if="form.errors.description" :class="helpClass">{{ form.errors.description }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Fabricante</span>
                <input v-model="form.manufacturer" :class="inputClass" type="text" maxlength="150" autocomplete="off">
                <p v-if="form.errors.manufacturer" :class="helpClass">{{ form.errors.manufacturer }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Modelo</span>
                <input v-model="form.model" :class="inputClass" type="text" maxlength="150" autocomplete="off">
                <p v-if="form.errors.model" :class="helpClass">{{ form.errors.model }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Número de série</span>
                <input v-model="form.serial_number" :class="inputClass" type="text" maxlength="150" autocomplete="off">
                <p v-if="form.errors.serial_number" :class="helpClass">{{ form.errors.serial_number }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Código patrimonial</span>
                <input v-model="form.asset_code" :class="inputClass" type="text" maxlength="120" autocomplete="off">
                <p v-if="form.errors.asset_code" :class="helpClass">{{ form.errors.asset_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Código ABC</span>
                <input v-model="form.abc_code" :class="inputClass" type="text" maxlength="20" autocomplete="off">
                <p v-if="form.errors.abc_code" :class="helpClass">{{ form.errors.abc_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Local de instalação</span>
                <input v-model="form.installation_location" :class="inputClass" type="text" maxlength="255" autocomplete="off">
                <p v-if="form.errors.installation_location" :class="helpClass">{{ form.errors.installation_location }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Data de comissionamento</span>
                <input v-model="form.commissioned_at" :class="inputClass" type="date">
                <p v-if="form.errors.commissioned_at" :class="helpClass">{{ form.errors.commissioned_at }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Observações</span>
                <textarea v-model="form.notes" :class="inputClass" rows="4" maxlength="10000"></textarea>
                <p v-if="form.errors.notes" :class="helpClass">{{ form.errors.notes }}</p>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Link
                :href="cancelUrl"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-900"
            >
                Cancelar
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ submitLabel }}
            </button>
        </div>
    </form>
</template>
