<script setup>
import GeneralAspectsNode from '@/components/domain/inspections/GeneralAspectsNode.vue';

defineProps({
    document: { type: Object, default: () => ({ type: 'doc', content: [] }) },
    images: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="general-aspects-content">
        <GeneralAspectsNode
            v-for="(node, index) in (document?.content || [])"
            :key="index"
            :node="node"
            :images="images"
        />
    </div>
</template>

<style>
.general-aspects-content { color: inherit; overflow-wrap: anywhere; }
.general-aspects-text-block { padding: 0; }
.general-aspects-paragraph { font-size: 1em; font-weight: 400; }
.general-aspects-heading-1 { font-size: 1.45em; font-weight: 800; }
.general-aspects-heading-2 { font-size: 1.25em; font-weight: 750; }
.general-aspects-heading-3 { font-size: 1.1em; font-weight: 700; }
.general-aspects-list { margin: 4pt 0 4pt 7mm; padding-left: 5mm; }
.general-aspects-list .general-aspects-list { margin-top: 2pt; margin-bottom: 2pt; }
.general-aspects-bullet-list { list-style: disc; }
.general-aspects-bullet-list .general-aspects-bullet-list { list-style: circle; }
.general-aspects-ordered-list { list-style: decimal; }
.general-aspects-list > li { padding-left: 1.5mm; }
.general-aspects-list-continuation { list-style: none; }
.general-aspects-list > li > .general-aspects-paragraph { display: inline; margin-left: 0 !important; }
.general-aspects-image { display: flex; justify-content: center; width: 100%; margin: .75rem 0; break-inside: avoid; }
.general-aspects-image img { display: block; max-width: 100%; height: auto; }
.general-aspects-table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: .75rem 0; }
.general-aspects-table th, .general-aspects-table td { border: 1px solid #64748b; padding: .35rem .45rem; vertical-align: top; overflow-wrap: anywhere; }
.general-aspects-table th { background: #e2e8f0; font-weight: 700; }
.general-aspects-table p { margin: 0; }
</style>
