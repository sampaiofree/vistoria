<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    equipmentId: { type: [String, Number], default: '' },
    inspectionId: { type: [String, Number], default: null },
    optionsUrl: { type: String, required: true },
    initialOptions: { type: Object, default: null },
    error: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue', 'base-change', 'status']);
const options = ref(null);
const state = ref('ready');
const search = ref('');
let controller;
let initialized = false;

const visible = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('pt-BR');
    return (options.value?.defects ?? []).filter((item) =>
        `${item.code} ${item.category} ${item.category_label}`.toLocaleLowerCase('pt-BR').includes(query));
});
const selected = computed(() => new Set(props.modelValue.map(Number)));

function setState(value) {
    state.value = value;
    emit('status', value);
}

function applyOptions(payload) {
    options.value = payload;
    emit('base-change', payload.previous_inspection_id);
    emit('update:modelValue', payload.selected_ids);
    setState('ready');
}

async function load() {
    controller?.abort();
    search.value = '';
    options.value = null;
    if (!props.equipmentId) {
        emit('update:modelValue', []);
        emit('base-change', null);
        setState('ready');
        return;
    }
    if (!initialized && props.initialOptions) {
        initialized = true;
        applyOptions(props.initialOptions);
        return;
    }
    initialized = true;
    emit('update:modelValue', []);
    emit('base-change', null);
    setState('loading');
    const current = new AbortController();
    controller = current;
    try {
        const url = new URL(props.optionsUrl, window.location.origin);
        url.searchParams.set('equipment_id', props.equipmentId);
        if (props.inspectionId) url.searchParams.set('inspection_id', props.inspectionId);
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: current.signal });
        if (!response.ok) throw new Error('Falha ao carregar avarias');
        const payload = await response.json();
        if (!Array.isArray(payload.defects) || !Array.isArray(payload.selected_ids)) throw new Error('Resposta inválida');
        if (controller === current) applyOptions(payload);
    } catch (error) {
        if (error.name !== 'AbortError' && controller === current) setState('error');
    }
}

function toggle(item, checked) {
    if (item.must_reinspect) return;
    const values = new Set(selected.value);
    if (checked) values.add(item.id);
    else values.delete(item.id);
    emit('update:modelValue', [...values]);
}

function badgeStyle(color) {
    const backgroundColor = /^#[0-9a-f]{6}$/i.test(color ?? '') ? color : '#e2e8f0';
    const channels = [1, 3, 5].map(offset => parseInt(backgroundColor.slice(offset, offset + 2), 16) / 255)
        .map(value => value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4);
    const luminance = channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    return { backgroundColor, color: luminance > 0.179 ? '#111827' : '#ffffff' };
}

function closeDropdown(event) {
    event.currentTarget.open = false;
    event.currentTarget.querySelector('summary')?.focus();
}

watch(() => props.equipmentId, load, { immediate: true, flush: 'sync' });
onBeforeUnmount(() => controller?.abort());
</script>

<template>
    <div v-if="equipmentId" class="space-y-2 text-sm">
        <p v-if="state === 'loading'" role="status" class="text-slate-500">Carregando avarias do histórico…</p>
        <div v-else-if="state === 'error'" role="alert" class="flex items-center gap-3 text-rose-700">
            <span>Não foi possível carregar as avarias.</span>
            <button type="button" class="font-semibold underline" @click="load">Tentar novamente</button>
        </div>
        <template v-else-if="options?.inspection_type === 'reinspection'">
            <p class="font-medium text-slate-700">Avarias a reinspecionar</p>
            <details v-if="options.defects.length" class="relative max-w-3xl rounded-lg border bg-white" :class="error ? 'border-rose-500' : 'border-slate-300'" @keydown.esc.prevent="closeDropdown">
                <summary class="cursor-pointer px-3 py-2 font-medium text-slate-700">
                    {{ modelValue.length }} de {{ options.defects.length }} selecionadas
                </summary>
                <div class="absolute z-40 mt-1 w-full min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                    <input v-model="search" type="search" aria-label="Pesquisar avarias por código ou categoria" placeholder="Pesquisar código ou categoria" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <button type="button" class="my-2 font-semibold text-teal-700" @click="emit('update:modelValue', options.defects.map((item) => item.id))">Selecionar todas</button>
                    <div class="max-h-72 space-y-1 overflow-y-auto">
                        <label v-for="item in visible" :key="item.id" class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-slate-50">
                            <input type="checkbox" :checked="selected.has(item.id)" :disabled="item.must_reinspect" class="mt-1 rounded border-slate-300 text-teal-700" @change="toggle(item, $event.target.checked)">
                            <span class="min-w-0 flex-1">
                                <span class="font-semibold text-slate-900">{{ item.code }}</span>
                                <span class="ml-2 text-slate-500">{{ item.category_label }}</span>
                                <span v-if="item.must_reinspect" class="mt-1 block text-xs text-amber-800">Sem avaliação publicada: reinspeção obrigatória.</span>
                            </span>
                            <span class="text-right">
                                <span class="whitespace-nowrap font-medium text-slate-800">{{ item.score_label }}</span>
                                <span v-if="item.classification_code" class="ml-2 inline-flex rounded-md px-2 py-1 text-xs font-bold" :style="badgeStyle(item.classification_color)">{{ item.classification_code }}</span>
                            </span>
                        </label>
                        <p v-if="!visible.length" class="p-2 text-slate-500">Nenhuma avaria encontrada.</p>
                    </div>
                </div>
            </details>
            <p v-else class="text-slate-500">Não há avarias ativas para reinspecionar.</p>
            <p class="text-xs text-slate-500">As avarias desmarcadas manterão os dados da última avaliação, com edição bloqueada.</p>
        </template>
        <p v-if="error" role="alert" class="text-xs text-rose-600">{{ error }}</p>
    </div>
</template>
