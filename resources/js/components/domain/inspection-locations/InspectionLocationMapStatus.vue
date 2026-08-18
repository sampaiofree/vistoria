<script setup>
const props = defineProps({ status: { type: String, required: true }, label: { type: String, default: '' } });
const styles = {
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-sky-100 text-sky-800',
    ready: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};
const labels = { pending: 'Pendente', processing: 'Processando', ready: 'Disponível', failed: 'Falha' };
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="styles[props.status] || 'bg-slate-100 text-slate-700'">
        {{ props.label || labels[props.status] || props.status }}
    </span>
</template>
