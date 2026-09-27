<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/components/ui/AppLayout.vue';

const props = defineProps({
    names: { type: Object, required: true },
    action: { type: String, required: true },
});
const form = useForm({
    report_reviewer_name: props.names.report_reviewer_name ?? '',
    report_releaser_name: props.names.report_releaser_name ?? '',
});
const submitting = ref(false);

function submit() {
    if (submitting.value) return;
    submitting.value = true;
    try {
        form.put(props.action, {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults({
                    report_reviewer_name: props.names.report_reviewer_name ?? '',
                    report_releaser_name: props.names.report_releaser_name ?? '',
                });
                form.reset();
            },
            onFinish: () => { submitting.value = false; },
        });
    } catch (error) {
        submitting.value = false;
        throw error;
    }
}
</script>

<template>
    <AppLayout title="Responsáveis do relatório" subtitle="Nomes de Revisor e Liberador exibidos nos relatórios da empresa.">
        <section class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-sm text-slate-600">Esses nomes aparecem no documento e não definem responsáveis ou permissões no sistema. Não é necessário que essas pessoas tenham uma conta de acesso.</p>
            <p class="mt-3 text-sm text-slate-600">As alterações se aplicam às inspeções abertas. Relatórios de inspeções liberadas ou canceladas mantêm os nomes gravados na finalização.</p>
            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <label v-for="field in [{ key: 'report_reviewer_name', label: 'Nome do Revisor' }, { key: 'report_releaser_name', label: 'Nome do Liberador' }]" :key="field.key" class="block">
                    <span class="text-sm font-semibold text-slate-700">{{ field.label }}</span>
                    <input v-model="form[field.key]" type="text" maxlength="150" :disabled="submitting" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:bg-slate-50">
                    <span v-if="form.errors[field.key]" class="mt-1 block text-xs text-rose-600">{{ form.errors[field.key] }}</span>
                </label>
                <p class="text-sm text-slate-500">Campos opcionais. Deixe em branco para exibir “Não definido” no relatório, sem impedir a liberação ou exportação.</p>
                <div class="flex justify-end border-t border-slate-100 pt-5">
                    <button type="submit" :disabled="submitting" :aria-busy="submitting" class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ submitting ? 'Salvando…' : 'Salvar' }}</button>
                </div>
            </form>
        </section>
    </AppLayout>
</template>
