<script setup>
const props = defineProps({
    form: { type: Object, required: true },
    submitLabel: { type: String, default: 'Salvar' },
    cancelUrl: { type: String, required: true },
    category: { type: Object, required: true },
});

defineEmits(['submit']);

const fallbackPickerColor = '#0F766E';

function normalizeColor(value) {
    return String(value ?? '').trim().replace(/\s+/g, '').toUpperCase();
}

function hasValidColor(value) {
    return /^#[0-9A-F]{6}$/.test(normalizeColor(value));
}

function pickerValue() {
    return hasValidColor(props.form.color) ? normalizeColor(props.form.color) : fallbackPickerColor;
}

function updateColor(value) {
    props.form.color = normalizeColor(value);
    props.form.clearErrors?.('color');
}
</script>

<template>
    <form class="space-y-5" @submit.prevent="$emit('submit')">
        <div class="rounded-xl border border-teal-100 bg-teal-50 p-4 text-sm text-teal-900">
            Categoria: <strong>{{ category.name }} ({{ category.code }})</strong>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Código</span>
                <input v-model="form.code" type="text" maxlength="30" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase">
                <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">{{ form.errors.code }}</p>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Nome</span>
                <input v-model="form.name" type="text" maxlength="150" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Posição</span>
                <input v-model.number="form.position" type="number" min="1" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p v-if="form.errors.position" class="mt-1 text-xs text-rose-600">{{ form.errors.position }}</p>
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-700">Criticidade</span>
                <input v-model.number="form.severity_rank" type="number" min="1" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p class="mt-1 text-xs text-slate-500">Menor número representa maior criticidade.</p>
                <p v-if="form.errors.severity_rank" class="mt-1 text-xs text-rose-600">{{ form.errors.severity_rank }}</p>
            </label>
            <fieldset class="md:col-span-2">
                <legend class="text-sm font-semibold text-slate-700">Cor</legend>
                <div class="mt-1.5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center">
                    <input
                        :value="pickerValue()"
                        type="color"
                        aria-label="Selecionar cor da classificação"
                        class="h-11 w-16 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                        @input="updateColor($event.target.value)"
                    >
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Cor hexadecimal</span>
                        <input
                            :value="form.color"
                            type="text"
                            required
                            maxlength="7"
                            pattern="#[0-9A-Fa-f]{6}"
                            placeholder="#0F766E"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-mono text-sm uppercase"
                            @input="updateColor($event.target.value)"
                        >
                    </label>
                    <div class="flex min-w-40 items-center gap-2 text-xs font-medium" :class="hasValidColor(form.color) ? 'text-slate-600' : 'text-amber-700'">
                        <span
                            v-if="hasValidColor(form.color)"
                            class="h-6 w-6 shrink-0 rounded-full border border-black/10 shadow-sm"
                            :style="{ backgroundColor: normalizeColor(form.color) }"
                        ></span>
                        <span v-else class="h-6 w-6 shrink-0 rounded-full border border-dashed border-slate-400 bg-white"></span>
                        {{ hasValidColor(form.color) ? normalizeColor(form.color) : 'Nenhuma cor selecionada' }}
                    </div>
                </div>
                <p class="mt-1 text-xs text-slate-500">Informe uma cor hexadecimal no formato #RRGGBB.</p>
                <p v-if="form.errors.color" class="mt-1 text-xs text-rose-600">{{ form.errors.color }}</p>
            </fieldset>
            <label class="block md:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Descrição</span>
                <textarea v-model="form.description" rows="4" maxlength="5000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
            </label>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <a :href="cancelUrl" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</a>
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60">{{ submitLabel }}</button>
        </div>
    </form>
</template>
