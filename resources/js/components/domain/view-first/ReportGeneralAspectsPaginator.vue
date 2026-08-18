<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';

const props = defineProps({
    document: { type: Object, default: null },
});

const emit = defineEmits(['pages', 'ready']);
const probe = ref(null);
const probeDocument = ref({ type: 'doc', content: [] });
let runId = 0;

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function listAtoms(node) {
    const start = Number(node.attrs?.start || 1);
    return (node.content || []).map((item, index) => ({
        type: node.type,
        ...(node.type === 'orderedList' && start + index !== 1 ? { attrs: { start: start + index } } : {}),
        content: [clone(item)],
    }));
}

function atomsFromDocument(document) {
    return (document?.content || []).flatMap((node) => (
        ['orderedList', 'bulletList'].includes(node.type) ? listAtoms(node) : [clone(node)]
    ));
}

function inlineTokens(node) {
    return (node.content || []).flatMap((child) => {
        if (child.type === 'hardBreak') return [clone(child)];
        if (child.type !== 'text') return [];

        const pieces = child.text.match(/\S+\s*|\s+/gu) || [child.text];
        return pieces.flatMap((piece) => {
            const characters = Array.from(piece);
            if (characters.length <= 200) return [{ ...clone(child), text: piece }];

            const chunks = [];
            for (let index = 0; index < characters.length; index += 200) {
                chunks.push({ ...clone(child), text: characters.slice(index, index + 200).join('') });
            }
            return chunks;
        });
    });
}

function textBlockWithTokens(node, tokens) {
    const result = { type: node.type };
    if (node.attrs) result.attrs = clone(node.attrs);
    if (node.reportNumber) result.reportNumber = node.reportNumber;
    if (tokens.length) result.content = tokens;
    return result;
}

async function fits(nodes) {
    probeDocument.value = { type: 'doc', content: clone(nodes) };
    await nextTick();
    return probe.value ? probe.value.scrollHeight <= probe.value.clientHeight + 1 : true;
}

async function splitOversizedTextBlock(node, prefix = []) {
    if (!['paragraph', 'heading'].includes(node.type)) return null;
    const tokens = inlineTokens(node);
    if (tokens.length < 2) return null;

    let low = 1;
    let high = tokens.length - 1;
    let best = 0;
    while (low <= high) {
        const middle = Math.floor((low + high) / 2);
        if (await fits([...prefix, textBlockWithTokens(node, tokens.slice(0, middle))])) {
            best = middle;
            low = middle + 1;
        } else {
            high = middle - 1;
        }
    }

    if (best === 0 || best >= tokens.length) return null;

    return [
        textBlockWithTokens(node, tokens.slice(0, best)),
        textBlockWithTokens({ ...node, type: 'paragraph' }, tokens.slice(best)),
    ];
}

function listWithParagraph(node, paragraph, continuation, includeRemainingChildren) {
    const result = clone(node);
    const originalItem = node.content[0];
    result.content = [{
        type: 'listItem',
        content: [paragraph, ...(includeRemainingChildren ? clone(originalItem.content || []).slice(1) : [])],
    }];
    if (continuation) result.reportContinuation = true;
    return result;
}

async function splitOversizedList(node, prefix = []) {
    if (!['orderedList', 'bulletList'].includes(node.type)) return null;
    const paragraph = node.content?.[0]?.content?.[0];
    if (!paragraph || paragraph.type !== 'paragraph') return null;
    const tokens = inlineTokens(paragraph);
    if (tokens.length < 2) return null;

    let low = 1;
    let high = tokens.length - 1;
    let best = 0;
    while (low <= high) {
        const middle = Math.floor((low + high) / 2);
        const fragment = listWithParagraph(
            node,
            textBlockWithTokens(paragraph, tokens.slice(0, middle)),
            false,
            false,
        );
        if (await fits([...prefix, fragment])) {
            best = middle;
            low = middle + 1;
        } else {
            high = middle - 1;
        }
    }

    if (best === 0 || best >= tokens.length) return null;

    return [
        listWithParagraph(node, textBlockWithTokens(paragraph, tokens.slice(0, best)), false, false),
        listWithParagraph(node, textBlockWithTokens(paragraph, tokens.slice(best)), true, true),
    ];
}

async function splitNode(node, prefix = []) {
    return await splitOversizedTextBlock(node, prefix)
        ?? await splitOversizedList(node, prefix);
}

async function paginate() {
    const thisRun = ++runId;
    emit('ready', false);

    const queue = atomsFromDocument(props.document);
    if (!queue.length) {
        emit('pages', []);
        emit('ready', true);
        return;
    }

    const result = [];
    let current = [];

    while (queue.length && thisRun === runId) {
        const node = queue.shift();
        const candidate = [...current, node];

        if (await fits(candidate)) {
            if (node.type === 'heading' && queue.length && !await fits([...candidate, queue[0]]) && current.length) {
                result.push({ type: 'doc', content: current });
                current = [node];
            } else {
                current = candidate;
            }
            continue;
        }

        if (current.length) {
            if (current.length === 1 && current[0].type === 'heading') {
                const splitAfterHeading = await splitNode(node, current);
                if (splitAfterHeading) {
                    result.push({ type: 'doc', content: [...current, splitAfterHeading[0]] });
                    current = [];
                    queue.unshift(splitAfterHeading[1]);
                    continue;
                }
            }

            result.push({ type: 'doc', content: current });
            current = [];
            queue.unshift(node);
            continue;
        }

        const split = await splitNode(node);
        if (split) {
            queue.unshift(split[1]);
            current = [split[0]];
            continue;
        }

        // A single indivisible item (for example, a deeply nested list item)
        // receives its own page. The page remains printable and no content is discarded.
        result.push({ type: 'doc', content: [node] });
    }

    if (current.length) result.push({ type: 'doc', content: current });
    if (thisRun !== runId) return;

    emit('pages', result);
    emit('ready', true);
}

watch(() => props.document, paginate, { deep: true });
onMounted(paginate);
</script>

<template>
    <div class="report-general-aspects-measure" aria-hidden="true">
        <div ref="probe" class="report-general-aspects-probe">
            <GeneralAspectsDocument :document="probeDocument" />
        </div>
    </div>
</template>

<style>
.report-general-aspects-measure { position: fixed; left: -10000px; top: 0; width: 182mm; visibility: hidden; pointer-events: none; }
.report-general-aspects-probe { width: 182mm; height: 237mm; overflow: auto; color: #111827; font-family: Georgia, 'Times New Roman', serif; font-size: 10pt; }
</style>
