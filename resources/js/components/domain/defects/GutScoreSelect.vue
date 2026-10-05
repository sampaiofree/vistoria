<script setup>
import { computed, nextTick, ref } from 'vue';
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/vue';
import { catalogOptionsAreSearchable, filterCatalogOptions } from '@/lib/catalogOptionSearch';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Selecione uma opção' },
    criterion: { type: String, default: '' },
    ariaLabel: { type: String, required: true },
    valueKey: { type: String, default: 'code' },
    disabled: { type: Boolean, default: false },
    searchThreshold: { type: Number, default: 8 },
});

const emit = defineEmits(['update:modelValue', 'change']);
const query = ref('');
const searchInput = ref(null);

function valueFor(option) {
    return option?.[props.valueKey] ?? null;
}

const selected = computed(() => props.options.find(
    (option) => String(valueFor(option)) === String(props.modelValue),
) ?? null);
const searchable = computed(() => catalogOptionsAreSearchable(props.options, props.searchThreshold));
const filteredOptions = computed(() => filterCatalogOptions(props.options, query.value));

function scoreLabel(option) {
    return !props.criterion || option.score === null || option.score === undefined
        ? null
        : `${props.criterion} = ${option.score}`;
}

async function prepareSearch() {
    query.value = '';

    if (!searchable.value) return;
    await nextTick();
    searchInput.value?.$el?.focus();
}

function selectValue(value) {
    query.value = '';
    emit('update:modelValue', value);
    emit('change', value);
}
</script>

<template>
    <component :is="searchable ? Combobox : Listbox" :model-value="modelValue" :disabled="disabled" v-bind="searchable ? { nullable: true } : {}" @update:model-value="selectValue">
        <div class="relative mt-1.5 min-w-0 max-w-full">
            <component
                :is="searchable ? ComboboxButton : ListboxButton"
                :aria-label="ariaLabel"
                class="flex min-h-11 w-full min-w-0 max-w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-left text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                @click="prepareSearch"
            >
                <span v-if="selected" class="flex min-w-0 flex-1 items-center gap-2.5">
                    <span v-if="selected.color" class="h-3.5 w-3.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: selected.color }" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="flex min-w-0 items-center gap-2">
                            <span v-if="scoreLabel(selected)" class="shrink-0 font-bold">{{ scoreLabel(selected) }}</span>
                            <span class="min-w-0 truncate text-slate-600">{{ selected.label }}</span>
                        </span>
                        <span v-if="selected.description" class="block truncate text-xs text-slate-500">{{ selected.description }}</span>
                    </span>
                </span>
                <span v-else class="min-w-0 truncate text-slate-500">{{ placeholder }}</span>
                <span class="shrink-0 text-slate-400" aria-hidden="true">⌄</span>
            </component>

            <component :is="searchable ? ComboboxOptions : ListboxOptions" class="absolute z-30 mt-1 max-h-72 w-full max-w-full overflow-x-hidden overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg focus:outline-none">
                <li v-if="searchable" class="sticky top-0 z-10 bg-white p-1">
                    <ComboboxInput
                        ref="searchInput"
                        :display-value="() => query"
                        :aria-label="`Buscar em ${ariaLabel}`"
                        placeholder="Buscar por descrição, código ou nota"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        @change="query = $event.target.value"
                        @focus="query = ''"
                        @click.stop
                    />
                </li>
                <component
                    :is="searchable ? ComboboxOption : ListboxOption"
                    v-for="option in filteredOptions"
                    :key="String(valueFor(option))"
                    v-slot="{ active, selected: isSelected }"
                    :value="valueFor(option)"
                    :disabled="Boolean(option.disabled)"
                    as="template"
                >
                    <li
                        class="flex min-w-0 items-start gap-2.5 rounded-lg px-3 py-2.5 text-sm"
                        :class="[option.disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer', active ? 'bg-teal-600 text-white' : 'text-slate-700']"
                    >
                        <span v-if="option.color" class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: option.color }" aria-hidden="true" />
                        <span class="min-w-0 flex-1 break-words">
                            <span>
                                <span v-if="scoreLabel(option)" class="font-bold">{{ scoreLabel(option) }}</span>
                                <span :class="[scoreLabel(option) ? 'ml-2' : '', active ? 'text-teal-50' : 'text-slate-600']">{{ option.label }}</span>
                            </span>
                            <span v-if="option.description" class="mt-0.5 block text-xs" :class="active ? 'text-teal-50' : 'text-slate-500'">{{ option.description }}</span>
                        </span>
                        <span v-if="isSelected" class="shrink-0 font-bold" aria-label="Selecionada">✓</span>
                    </li>
                </component>
                <li v-if="searchable && filteredOptions.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                    Nenhuma opção encontrada.
                </li>
            </component>
        </div>
    </component>
</template>
