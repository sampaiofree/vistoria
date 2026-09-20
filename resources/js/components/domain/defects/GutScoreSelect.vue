<script setup>
import { computed } from 'vue';
import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Selecione uma opção' },
    criterion: { type: String, required: true },
    ariaLabel: { type: String, required: true },
    valueKey: { type: String, default: 'code' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function valueFor(option) {
    return option?.[props.valueKey] ?? null;
}

const selected = computed(() => props.options.find(
    (option) => String(valueFor(option)) === String(props.modelValue),
) ?? null);

function scoreLabel(option) {
    return option.score === null || option.score === undefined
        ? null
        : `${props.criterion} = ${option.score}`;
}
</script>

<template>
    <Listbox :model-value="modelValue" :disabled="disabled" @update:model-value="emit('update:modelValue', $event)">
        <div class="relative mt-1.5">
            <ListboxButton
                :aria-label="ariaLabel"
                class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-left text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 disabled:cursor-not-allowed disabled:bg-slate-100"
            >
                <span v-if="selected" class="flex min-w-0 items-center gap-2.5">
                    <span v-if="selected.color" class="h-3.5 w-3.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: selected.color }" aria-hidden="true" />
                    <span v-if="scoreLabel(selected)" class="shrink-0 font-bold">{{ scoreLabel(selected) }}</span>
                    <span class="truncate text-slate-600">{{ selected.label }}</span>
                </span>
                <span v-else class="text-slate-500">{{ placeholder }}</span>
                <span class="shrink-0 text-slate-400" aria-hidden="true">⌄</span>
            </ListboxButton>

            <ListboxOptions class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg focus:outline-none">
                <ListboxOption
                    v-for="option in options"
                    :key="String(valueFor(option))"
                    v-slot="{ active, selected: isSelected }"
                    :value="valueFor(option)"
                    as="template"
                >
                    <li
                        class="flex cursor-pointer items-start gap-2.5 rounded-lg px-3 py-2.5 text-sm"
                        :class="active ? 'bg-teal-600 text-white' : 'text-slate-700'"
                    >
                        <span v-if="option.color" class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: option.color }" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span v-if="scoreLabel(option)" class="font-bold">{{ scoreLabel(option) }}</span>
                            <span :class="[scoreLabel(option) ? 'ml-2' : '', active ? 'text-teal-50' : 'text-slate-600']">{{ option.label }}</span>
                        </span>
                        <span v-if="isSelected" class="shrink-0 font-bold" aria-label="Selecionada">✓</span>
                    </li>
                </ListboxOption>
            </ListboxOptions>
        </div>
    </Listbox>
</template>
