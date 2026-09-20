<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    inspection: { type: Object, required: true },
    capability: { type: [Object, Boolean], default: false },
});

const editing = ref(false);
const form = useForm({ report_revision: props.inspection.report_revision ?? 0 });
const canEdit = computed(() => Boolean(props.capability?.action));

watch(() => props.inspection.report_revision, (revision) => {
    form.defaults({ report_revision: revision ?? 0 });
    if (!editing.value) form.reset();
});

function cancel() {
    editing.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.put(props.capability.action, {
        preserveScroll: true,
        only: ['inspection', 'capabilities', 'flash'],
        onSuccess: () => {
            editing.value = false;
            form.clearErrors();
        },
    });
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Identificação</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Revisão</h2>
                <p class="mt-1 text-sm text-slate-500">Número sequencial reservado para o relatório deste equipamento.</p>
            </div>
            <button v-if="canEdit && !editing" type="button" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400" @click="editing = true">Editar</button>
        </div>

        <form v-if="editing" class="mt-5 flex flex-wrap items-start gap-3 border-t border-slate-100 pt-5" @submit.prevent="submit">
            <label class="w-full max-w-48 space-y-1.5 text-sm font-medium text-slate-700">
                <span>Revisão</span>
                <input v-model.number="form.report_revision" type="number" min="0" step="1" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                <span v-if="form.errors.report_revision" class="block text-xs text-rose-600">{{ form.errors.report_revision }}</span>
            </label>
            <div class="flex gap-3 pt-7">
                <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="cancel">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50">Salvar</button>
            </div>
        </form>
        <p v-else class="mt-5 border-t border-slate-100 pt-5 text-2xl font-semibold text-slate-950">{{ inspection.report_revision ?? '—' }}</p>
    </section>
</template>
