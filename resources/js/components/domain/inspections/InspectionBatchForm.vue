<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';

const props = defineProps({
    previewAction: { type: String, required: true },
    confirmAction: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    equipmentSearchUrl: { type: String, required: true },
    selectedEquipment: { type: Array, default: () => [] },
    inspectors: { type: Array, default: () => [] },
    preview: { type: Object, default: null },
});

let nextInspectionKey = 0;

function blankInspection() {
    return {
        row_key: `inspection-${++nextInspectionKey}`,
        equipment_id: '',
        equipment_search: '',
        service_order: '',
        planned_start_on: '',
        planned_end_on: '',
        inspector_id: '',
    };
}

function hydrateInspection(inspection) {
    return {
        ...blankInspection(),
        ...inspection,
    };
}

const form = useForm({
    inspections: props.preview?.inspections?.map(hydrateInspection) ?? [blankInspection()],
});
const confirming = ref(false);
const previewToken = computed(() => props.preview?.token ?? null);
const knownEquipment = ref({});
const equipmentOptionsByRow = ref({});
const loadingEquipmentByRow = ref({});
const equipmentSearchCache = new Map();
const equipmentSearchTimers = new Map();
const equipmentSearchControllers = new Map();

watch(
    () => props.preview?.token,
    () => {
        const inspections = props.preview?.inspections?.map(hydrateInspection) ?? [blankInspection()];

        form.inspections = inspections;
        form.defaults({ inspections });
        form.clearErrors();
    },
);

watch(
    () => props.selectedEquipment,
    (equipment) => rememberEquipment(equipment),
    { immediate: true },
);

function equipmentLabel(item) {
    return [item.maintenance_item_code, item.tag, item.description || item.name]
        .filter(Boolean)
        .join(' — ');
}

function rememberEquipment(equipment) {
    for (const item of equipment ?? []) {
        knownEquipment.value[item.id] = item;
    }
}

function selectedEquipment(row) {
    return knownEquipment.value[row.equipment_id] ?? null;
}

function rowOptions(row) {
    return equipmentOptionsByRow.value[row.row_key] ?? [];
}

function isSearchingEquipment(row) {
    return loadingEquipmentByRow.value[row.row_key] === true;
}

function clearPendingEquipmentSearch(row) {
    const timer = equipmentSearchTimers.get(row.row_key);

    if (timer) {
        clearTimeout(timer);
        equipmentSearchTimers.delete(row.row_key);
    }

    const controller = equipmentSearchControllers.get(row.row_key);

    if (controller) {
        controller.abort();
        equipmentSearchControllers.delete(row.row_key);
    }
}

function updateEquipmentSearch(row, value) {
    row.equipment_search = value;
    clearPendingEquipmentSearch(row);

    const search = value.trim();

    if (search.length < 2) {
        equipmentOptionsByRow.value[row.row_key] = [];
        loadingEquipmentByRow.value[row.row_key] = false;

        return;
    }

    loadingEquipmentByRow.value[row.row_key] = true;
    equipmentSearchTimers.set(row.row_key, setTimeout(() => {
        searchEquipment(row, search);
    }, 300));
}

async function searchEquipment(row, search) {
    const cacheKey = search.toLocaleLowerCase('pt-BR');

    if (equipmentSearchCache.has(cacheKey)) {
        const equipment = equipmentSearchCache.get(cacheKey);
        rememberEquipment(equipment);
        equipmentOptionsByRow.value[row.row_key] = equipment;
        loadingEquipmentByRow.value[row.row_key] = false;

        return;
    }

    const controller = new AbortController();
    equipmentSearchControllers.set(row.row_key, controller);

    try {
        const url = new URL(props.equipmentSearchUrl, window.location.origin);
        url.searchParams.set('search', search);

        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error('Não foi possível pesquisar equipamentos.');
        }

        const payload = await response.json();
        const equipment = Array.isArray(payload.equipment) ? payload.equipment : [];

        equipmentSearchCache.set(cacheKey, equipment);
        rememberEquipment(equipment);

        if (row.equipment_search.trim() === search) {
            equipmentOptionsByRow.value[row.row_key] = equipment;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            equipmentOptionsByRow.value[row.row_key] = [];
        }
    } finally {
        if (equipmentSearchControllers.get(row.row_key) === controller) {
            equipmentSearchControllers.delete(row.row_key);
            loadingEquipmentByRow.value[row.row_key] = false;
        }
    }
}

function selectEquipment(row, equipment) {
    if (equipment) {
        rememberEquipment([equipment]);
    }

    row.equipment_id = equipment?.id ?? '';
    row.equipment_search = '';
    equipmentOptionsByRow.value[row.row_key] = [];
    clearPendingEquipmentSearch(row);
    loadingEquipmentByRow.value[row.row_key] = false;
}

function addInspection() {
    form.inspections.push(blankInspection());
}

function removeInspection(index) {
    if (form.inspections.length > 1) {
        clearPendingEquipmentSearch(form.inspections[index]);
        form.inspections.splice(index, 1);
    }
}

onBeforeUnmount(() => {
    for (const inspection of form.inspections) {
        clearPendingEquipmentSearch(inspection);
    }
});

function fieldError(index, field) {
    return form.errors[`inspections.${index}.${field}`];
}

function previewBatch() {
    form.post(props.previewAction, {
        preserveScroll: true,
    });
}

function confirmBatch() {
    if (!previewToken.value || form.isDirty) {
        return;
    }

    confirming.value = true;
    router.post(props.confirmAction, { token: previewToken.value }, {
        preserveScroll: true,
        onFinish: () => {
            confirming.value = false;
        },
    });
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="previewBatch">
        <div v-if="previewToken" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">
            <p class="font-semibold">Prévia pronta para confirmação</p>
            <p class="mt-1">Confira os registros abaixo. Se fizer alterações, atualize a prévia antes de confirmar.</p>
        </div>

        <div class="space-y-4">
            <section
                v-for="(inspection, index) in form.inspections"
                :key="index"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3 xl:hidden">
                    <p class="text-sm font-semibold text-slate-900">Inspeção {{ index + 1 }}</p>
                    <button
                        type="button"
                        :disabled="form.inspections.length === 1"
                        class="rounded-lg p-2 text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300"
                        title="Remover registro"
                        @click="removeInspection(index)"
                    >
                        ×
                    </button>
                </div>

                <div class="grid gap-4 xl:grid-cols-[minmax(20rem,2fr)_minmax(8rem,0.75fr)_10rem_10rem_minmax(12rem,1fr)_2.5rem] xl:items-start">
                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>Item de manutenção / equipamento</span>
                        <Combobox
                            :model-value="selectedEquipment(inspection)"
                            @update:model-value="selectEquipment(inspection, $event)"
                        >
                            <div class="relative">
                                <ComboboxButton class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-slate-700">
                                    <span class="truncate">{{ selectedEquipment(inspection) ? equipmentLabel(selectedEquipment(inspection)) : 'Selecione o equipamento' }}</span>
                                    <span aria-hidden="true" class="text-slate-400">⌄</span>
                                </ComboboxButton>
                                <ComboboxOptions as="ul" class="absolute z-40 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg focus:outline-none">
                                    <li class="sticky top-0 bg-white p-1">
                                        <ComboboxInput
                                            :display-value="() => inspection.equipment_search"
                                            placeholder="Digite ao menos 2 caracteres"
                                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                            @input="updateEquipmentSearch(inspection, $event.target.value)"
                                        />
                                    </li>
                                    <ComboboxOption
                                        v-for="item in rowOptions(inspection)"
                                        :key="item.id"
                                        v-slot="{ active, selected }"
                                        :value="item"
                                        as="template"
                                    >
                                        <li class="cursor-pointer rounded-md px-3 py-2 text-sm" :class="active ? 'bg-teal-600 text-white' : 'text-slate-700'">
                                            <p :class="selected ? 'font-semibold' : 'font-medium'">{{ item.maintenance_item_code }} · {{ item.tag }}</p>
                                            <p class="mt-0.5 text-xs" :class="active ? 'text-teal-50' : 'text-slate-500'">{{ item.description || item.name }}</p>
                                        </li>
                                    </ComboboxOption>
                                    <li v-if="isSearchingEquipment(inspection)" class="px-3 py-2 text-sm text-slate-500">
                                        Pesquisando equipamentos…
                                    </li>
                                    <li v-else-if="inspection.equipment_search.trim().length < 2" class="px-3 py-2 text-sm text-slate-500">
                                        Digite ao menos 2 caracteres para pesquisar.
                                    </li>
                                    <li v-else-if="rowOptions(inspection).length === 0" class="px-3 py-2 text-sm text-slate-500">
                                        Nenhum equipamento encontrado.
                                    </li>
                                </ComboboxOptions>
                            </div>
                        </Combobox>
                        <span v-if="selectedEquipment(inspection)" class="block text-xs font-normal text-slate-500">{{ selectedEquipment(inspection).name }}</span>
                        <span v-if="fieldError(index, 'equipment_id')" class="block text-xs font-normal text-rose-600">{{ fieldError(index, 'equipment_id') }}</span>
                    </label>

                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>Ordem de serviço</span>
                        <input v-model="inspection.service_order" type="text" maxlength="100" placeholder="OS" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <span v-if="fieldError(index, 'service_order')" class="block text-xs font-normal text-rose-600">{{ fieldError(index, 'service_order') }}</span>
                    </label>

                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>Data inicial</span>
                        <input v-model="inspection.planned_start_on" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <span v-if="fieldError(index, 'planned_start_on')" class="block text-xs font-normal text-rose-600">{{ fieldError(index, 'planned_start_on') }}</span>
                    </label>

                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>Prazo final</span>
                        <input v-model="inspection.planned_end_on" type="date" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <span v-if="fieldError(index, 'planned_end_on')" class="block text-xs font-normal text-rose-600">{{ fieldError(index, 'planned_end_on') }}</span>
                    </label>

                    <label class="space-y-1.5 text-sm font-medium text-slate-700">
                        <span>Inspetor</span>
                        <select v-model="inspection.inspector_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                            <option value="" disabled>Selecione o Inspetor</option>
                            <option v-for="inspector in inspectors" :key="inspector.id" :value="inspector.id">{{ inspector.name }}</option>
                        </select>
                        <span v-if="fieldError(index, 'inspector_id')" class="block text-xs font-normal text-rose-600">{{ fieldError(index, 'inspector_id') }}</span>
                    </label>

                    <div class="hidden pt-7 text-right xl:block">
                        <button
                            type="button"
                            :disabled="form.inspections.length === 1"
                            class="rounded-lg p-2 text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:text-slate-300"
                            title="Remover registro"
                            @click="removeInspection(index)"
                        >
                            ×
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <button type="button" class="rounded-lg border border-teal-600 px-4 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50" @click="addInspection">
            + Adicionar inspeção
        </button>

        <p v-if="form.errors.inspections" class="text-sm text-rose-600">{{ form.errors.inspections }}</p>
        <p v-if="form.errors.actor" class="text-sm text-rose-600">{{ form.errors.actor }}</p>
        <p v-if="form.errors.token" class="text-sm text-rose-600">{{ form.errors.token }}</p>

        <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
            <Link :href="cancelUrl" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Cancelar</Link>
            <button :disabled="form.processing" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                {{ previewToken ? 'Atualizar prévia' : 'Revisar registros' }}
            </button>
            <button
                v-if="previewToken"
                type="button"
                :disabled="form.isDirty || confirming"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="confirmBatch"
            >
                Confirmar registros
            </button>
        </div>
    </form>
</template>
