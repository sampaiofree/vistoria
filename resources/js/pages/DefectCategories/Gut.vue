<script setup>
import { reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    category: { type: Object, required: true },
    gut: { type: Object, required: true },
    action: { type: String, required: true },
    cancel_url: { type: String, required: true },
});

const criteria = [
    { key: 'gravity', label: 'Gravidade (G)' },
    { key: 'urgency', label: 'Urgência (U)' },
    { key: 'trend', label: 'Tendência (T)' },
];

const rows = reactive({
    gravity: (props.gut.gravity ?? []).map((option) => ({ ...option })),
    urgency: (props.gut.urgency ?? []).map((option) => ({ ...option })),
    trend: (props.gut.trend ?? []).map((option) => ({ ...option })),
});

const form = useForm({ gut_options: [] });

function addOption(criterion) {
    rows[criterion].push({ score: '', color: '#FFFFFF' });
}

function removeOption(criterion, index) {
    rows[criterion].splice(index, 1);
}

function optionIndex(criterion, index) {
    return criteria
        .slice(0, criteria.findIndex((item) => item.key === criterion))
        .reduce((total, item) => total + rows[item.key].length, 0) + index;
}

function submit() {
    const options = criteria.flatMap(({ key }) => rows[key].map((option) => ({
        criterion: key,
        score: option.score,
        color: option.color,
    })));

    form.transform(() => ({ gut_options: options })).put(props.action, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="`GUT · ${category.name}`" :subtitle="`Categoria ${category.code}`">
        <section class="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-slate-900">Configuração GUT</h1>
                    <p class="mt-1 text-sm text-slate-500">Cadastre notas inteiras e suas cores. Os critérios podem ter quantidades diferentes ou ficar vazios.</p>
                </div>
                <Link :href="cancel_url" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancelar</Link>
            </div>

            <p v-if="form.errors.gut_options" class="mt-4 text-sm text-rose-600">{{ form.errors.gut_options }}</p>

            <form class="mt-6 grid gap-5 lg:grid-cols-3" @submit.prevent="submit">
                <section v-for="criterion in criteria" :key="criterion.key" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-slate-900">{{ criterion.label }}</h2>
                        <button type="button" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700" @click="addOption(criterion.key)">Adicionar</button>
                    </div>

                    <div v-if="rows[criterion.key].length" class="mt-4 space-y-3">
                        <div v-for="(option, index) in rows[criterion.key]" :key="option.id ?? `${criterion.key}-${index}`" class="grid grid-cols-[1fr_auto] items-end gap-2">
                            <label class="block">
                                <span class="text-xs font-semibold text-slate-600">Nota</span>
                                <input v-model.number="option.score" type="number" min="0" max="65535" step="1" required class="mt-1 block min-h-10 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <div class="flex items-end gap-2">
                                <label class="block">
                                    <span class="text-xs font-semibold text-slate-600">Cor</span>
                                    <input v-model="option.color" type="color" class="mt-1 block h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1" aria-label="Cor da nota">
                                </label>
                                <button type="button" class="mb-1 rounded-lg px-2 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="removeOption(criterion.key, index)">Remover</button>
                            </div>
                            <input v-model="option.color" type="text" maxlength="7" pattern="#[0-9A-Fa-f]{6}" class="col-span-2 min-h-9 rounded-lg border border-slate-300 px-3 py-1.5 font-mono text-xs uppercase" aria-label="Código hexadecimal da cor">
                            <p v-if="form.errors[`gut_options.${optionIndex(criterion.key, index)}.score`] || form.errors[`gut_options.${optionIndex(criterion.key, index)}.color`]" class="col-span-2 text-xs text-rose-600">{{ form.errors[`gut_options.${optionIndex(criterion.key, index)}.score`] || form.errors[`gut_options.${optionIndex(criterion.key, index)}.color`] }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">Critério não configurado.</p>
                </section>

                <div class="flex justify-end gap-3 lg:col-span-3">
                    <Link :href="cancel_url" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancelar</Link>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-slate-800 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Salvando…' : 'Salvar configuração' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
