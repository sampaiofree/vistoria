<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    form: {
        type: Object,
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
    prefixEditable: {
        type: Boolean,
        default: true,
    },
    abcOptions: {
        type: Array,
        default: () => [],
    },
    identifierContext: {
        type: Object,
        default: () => ({ client_name: null, organization_name: null }),
    },
});

defineEmits(['submit']);

const inputClass = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100';
const labelClass = 'text-sm font-medium text-slate-700';
const helpClass = 'mt-1 text-xs text-rose-600';
const customerNumberLabel = computed(() => props.identifierContext.client_name
    ? `Número do cliente (${props.identifierContext.client_name})`
    : 'Número do cliente');
const internalNumberLabel = computed(() => props.identifierContext.organization_name
    ? `Número interno (${props.identifierContext.organization_name})`
    : 'Número interno');
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span :class="labelClass">{{ customerNumberLabel }} <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.numero_cliente" :class="inputClass" type="text" maxlength="50" autocomplete="off" required>
                <p v-if="form.errors.numero_cliente" :class="helpClass">{{ form.errors.numero_cliente }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">{{ internalNumberLabel }} <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.numero_interno" :class="inputClass" type="text" maxlength="50" autocomplete="off" required>
                <p v-if="form.errors.numero_interno" :class="helpClass">{{ form.errors.numero_interno }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Plano de manutenção</span>
                <input v-model="form.maintenance_plan_code" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.maintenance_plan_code" :class="helpClass">{{ form.errors.maintenance_plan_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Item manutenção <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.maintenance_item_code" :class="inputClass" type="text" maxlength="80" autocomplete="off" required>
                <p v-if="form.errors.maintenance_item_code" :class="helpClass">{{ form.errors.maintenance_item_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Area(usina)</span>
                <input v-model="form.area_code" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.area_code" :class="helpClass">{{ form.errors.area_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Area.nome</span>
                <input v-model="form.area_name" :class="inputClass" type="text" maxlength="180" autocomplete="off">
                <p v-if="form.errors.area_name" :class="helpClass">{{ form.errors.area_name }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Sub-area</span>
                <input v-model="form.subarea_code" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.subarea_code" :class="helpClass">{{ form.errors.subarea_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">sub-area.nome</span>
                <input v-model="form.subarea_name" :class="inputClass" type="text" maxlength="180" autocomplete="off">
                <p v-if="form.errors.subarea_name" :class="helpClass">{{ form.errors.subarea_name }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">GrpLisTar.</span>
                <input v-model="form.task_list_group" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.task_list_group" :class="helpClass">{{ form.errors.task_list_group }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Numerador de grupos</span>
                <input v-model="form.task_list_group_counter" :class="inputClass" type="text" maxlength="80" autocomplete="off">
                <p v-if="form.errors.task_list_group_counter" :class="helpClass">{{ form.errors.task_list_group_counter }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Campo de ordenação (TAG) <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.tag" :class="inputClass" type="text" maxlength="120" autocomplete="off" required>
                <p class="mt-1 text-xs text-slate-500">O valor será normalizado automaticamente.</p>
                <p v-if="form.errors.tag" :class="helpClass">{{ form.errors.tag }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Prefixo de avaria <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.defect_code_prefix" :class="inputClass" type="text" maxlength="80" autocomplete="off" :disabled="!prefixEditable" required>
                <p class="mt-1 text-xs text-slate-500">Obrigatório e único na organização. Depois da primeira avaria, não pode ser alterado.</p>
                <p v-if="form.errors.defect_code_prefix" :class="helpClass">{{ form.errors.defect_code_prefix }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Denominação do loc.instalação <span class="text-rose-600" aria-hidden="true">*</span></span>
                <input v-model="form.name" :class="inputClass" type="text" maxlength="180" autocomplete="off" required>
                <p v-if="form.errors.name" :class="helpClass">{{ form.errors.name }}</p>
            </label>

            <label class="block lg:col-span-2">
                <span :class="labelClass">Descrição item de manutenção</span>
                <textarea v-model="form.description" :class="inputClass" rows="4" maxlength="10000"></textarea>
                <p v-if="form.errors.description" :class="helpClass">{{ form.errors.description }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Código ABC</span>
                <select v-model="form.abc_code" :class="inputClass">
                    <option value="">Não informado</option>
                    <option v-for="option in abcOptions" :key="option.value" :value="option.value">
                        {{ option.value }} — {{ option.label }}
                    </option>
                </select>
                <p class="mt-1 text-xs text-slate-500">Usado para calcular a gravidade G das avarias TAC.</p>
                <p v-if="form.errors.abc_code" :class="helpClass">{{ form.errors.abc_code }}</p>
            </label>

            <label class="block">
                <span :class="labelClass">Local de instalação</span>
                <input v-model="form.installation_location" :class="inputClass" type="text" maxlength="255" autocomplete="off">
                <p v-if="form.errors.installation_location" :class="helpClass">{{ form.errors.installation_location }}</p>
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
