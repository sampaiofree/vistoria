<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    references: { type: Object, default: () => ({}) },
    capability: { type: [Object, Boolean], default: false },
});

const editing = ref(false);
const form = useForm({
    general_drawing: props.references.general_drawing ?? '',
    procedure_number: props.references.procedure_number ?? '',
});
const canEdit = computed(() => Boolean(props.capability?.action));

function syncForm() {
    form.defaults({
        general_drawing: props.references.general_drawing ?? '',
        procedure_number: props.references.procedure_number ?? '',
    });
    if (!editing.value) form.reset();
}

watch(() => props.references, syncForm, { deep: true });

function startEditing() {
    form.reset();
    form.clearErrors();
    editing.value = true;
}

function cancel() {
    editing.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (!canEdit.value) return;

    form.put(props.capability.action, {
        preserveScroll: true,
        only: ['technical_references', 'capabilities', 'flash'],
        onSuccess: () => {
            editing.value = false;
            form.clearErrors();
        },
    });
}
</script>

<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700">Identificação</p>
                <h2 class="mt-2 text-xl font-semibold text-slate-950">Referências técnicas</h2>
                <p class="mt-1 text-sm text-slate-500">DESENHO GERAL e PROC. INSPEÇÃO são obrigatórios para avançar a inspeção. Você pode salvar um campo por vez.</p>
            </div>
            <button v-if="canEdit && !editing" type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="startEditing">Editar</button>
        </div>

        <form v-if="editing && canEdit" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2" @submit.prevent="submit">
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>DESENHO GERAL</span>
                <input v-model="form.general_drawing" type="text" maxlength="150" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                <span v-if="form.errors.general_drawing" class="block text-xs text-rose-600">{{ form.errors.general_drawing }}</span>
            </label>
            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                <span>PROC. INSPEÇÃO</span>
                <input v-model="form.procedure_number" type="text" maxlength="150" class="w-full rounded-xl border border-slate-300 px-3 py-2.5">
                <span v-if="form.errors.procedure_number" class="block text-xs text-rose-600">{{ form.errors.procedure_number }}</span>
            </label>
            <div class="flex gap-3 sm:col-span-2">
                <button type="button" class="rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700" @click="cancel">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-teal-700 px-3.5 py-2 text-sm font-semibold text-white disabled:opacity-50">Salvar</button>
            </div>
        </form>
        <div v-else class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
            <div><p class="text-sm text-slate-500">DESENHO GERAL</p><p class="mt-1 font-semibold text-slate-950">{{ references.general_drawing || '—' }}</p></div>
            <div><p class="text-sm text-slate-500">PROC. INSPEÇÃO</p><p class="mt-1 font-semibold text-slate-950">{{ references.procedure_number || '—' }}</p></div>
        </div>
    </section>
</template>
