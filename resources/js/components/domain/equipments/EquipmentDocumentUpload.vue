<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    documentTypes: {
        type: Array,
        required: true,
    },
    documents: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    document_type: '',
    title: '',
    document_number: '',
    revision: '',
    description: '',
    issued_at: '',
    document_group: '',
    file: null,
});

const existingGroups = computed(() => {
    const groups = new Map();

    for (const document of props.documents) {
        if (!groups.has(document.document_group)) {
            groups.set(document.document_group, `${document.title} · ${document.document_type_label}`);
        }
    }

    return Array.from(groups.entries()).map(([value, label]) => ({
        value,
        label,
    }));
});

function handleFileChange(event) {
    form.file = event.target.files?.[0] ?? null;
}

function submit() {
    form.post(props.action, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            form.file = null;
        },
    });
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-1">
            <h3 class="text-lg font-semibold text-slate-900">Novo documento</h3>
            <p class="text-sm text-slate-500">
                Envie uma nova revisão ou crie um novo grupo documental para este equipamento.
            </p>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Tipo</span>
                    <select
                        v-model="form.document_type"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                        <option value="">Selecione</option>
                        <option v-for="option in documentTypes" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.document_type" class="mt-1 text-xs text-rose-600">{{ form.errors.document_type }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Título</span>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="200"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                    <p v-if="form.errors.title" class="mt-1 text-xs text-rose-600">{{ form.errors.title }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Número do documento</span>
                    <input
                        v-model="form.document_number"
                        type="text"
                        maxlength="150"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                    <p v-if="form.errors.document_number" class="mt-1 text-xs text-rose-600">{{ form.errors.document_number }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Revisão</span>
                    <input
                        v-model="form.revision"
                        type="text"
                        maxlength="50"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                    <p v-if="form.errors.revision" class="mt-1 text-xs text-rose-600">{{ form.errors.revision }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Grupo documental</span>
                    <select
                        v-model="form.document_group"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                        <option value="">Novo grupo</option>
                        <option v-for="group in existingGroups" :key="group.value" :value="group.value">
                            {{ group.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Deixe em branco para criar um grupo novo.</p>
                    <p v-if="form.errors.document_group" class="mt-1 text-xs text-rose-600">{{ form.errors.document_group }}</p>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">Data de emissão</span>
                    <input
                        v-model="form.issued_at"
                        type="date"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                    <p v-if="form.errors.issued_at" class="mt-1 text-xs text-rose-600">{{ form.errors.issued_at }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Descrição</span>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        maxlength="10000"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-rose-600">{{ form.errors.description }}</p>
                </label>

                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700">Arquivo</span>
                    <input
                        type="file"
                        accept=".pdf,.xlsx,.xlsm,.doc,.docx,.png,.jpg,.jpeg,.webp"
                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        @change="handleFileChange"
                    >
                    <p class="mt-1 text-xs text-slate-500">PDF, Office ou imagem, até 25 MB.</p>
                    <p v-if="form.errors.file" class="mt-1 text-xs text-rose-600">{{ form.errors.file }}</p>
                </label>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-slate-500">
                    O arquivo será armazenado em disco privado.
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    Enviar documento
                </button>
            </div>
        </form>
    </section>
</template>
