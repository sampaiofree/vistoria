<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import GeneralAspectsDocument from '@/components/domain/inspections/GeneralAspectsDocument.vue';

const props = defineProps({
    document: { type: Object, default: null },
    images: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['pages', 'ready', 'error']);
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
    return (document?.content || []).flatMap((node, index) => (
        ['orderedList', 'bulletList'].includes(node.type) ? listAtoms(node) : [
            node.type === 'table' ? { ...clone(node), reportTableId: index } : clone(node),
        ]
    ));
}

function appended(nodes, node) {
    const last = nodes.at(-1);
    if (node.type !== 'table' || last?.type !== 'table' || last.reportTableId !== node.reportTableId) {
        return [...nodes, node];
    }
    const headerRepeated = node.content?.[0]?.content?.every((cell) => cell.type === 'tableHeader');
    return [...nodes.slice(0, -1), {
        ...last,
        content: [...last.content, ...(headerRepeated ? node.content.slice(1) : node.content)],
    }];
}

function tableFragment(node, rows) {
    return { type: 'table', content: rows, reportTableId: node.reportTableId };
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
    await Promise.all(Array.from(probe.value?.querySelectorAll('img') || []).map(async (image) => {
        if (!image.getAttribute('src')) throw new Error('Uma imagem dos Aspectos Gerais não está disponível.');
        if (!image.complete) await image.decode();
        if (!image.naturalWidth) throw new Error('Não foi possível carregar uma imagem dos Aspectos Gerais.');
    }));
    await nextTick();
    return probe.value ? probe.value.scrollHeight <= probe.value.clientHeight + 1 : true;
}

function tableRowWithTokens(row, tokenSets) {
    const result = clone(row);
    result.content = result.content.map((cell, index) => ({ ...cell, content: [{
        type: 'paragraph', ...(tokenSets[index].length ? { content: tokenSets[index] } : {}),
    }] }));
    return result;
}

async function splitOversizedTableRow(row, prefix = [], header = null) {
    const tokenSets = row.content.map((cell) => inlineTokens(cell.content?.[0] || {}));
    const max = Math.max(...tokenSets.map((tokens) => tokens.length));
    if (max < 2) return null;
    let low = 1;
    let high = max - 1;
    let best = 0;
    while (low <= high) {
        const middle = Math.floor((low + high) / 2);
        const fragment = tableRowWithTokens(row, tokenSets.map((tokens) => tokens.slice(0, middle)));
        if (await fits([...prefix, { type: 'table', content: header ? [header, fragment] : [fragment] }])) {
            best = middle;
            low = middle + 1;
        } else high = middle - 1;
    }
    if (!best) return null;
    return [
        tableRowWithTokens(row, tokenSets.map((tokens) => tokens.slice(0, best))),
        tableRowWithTokens(row, tokenSets.map((tokens) => tokens.slice(best))),
    ];
}

async function splitOversizedTable(node, prefix = []) {
    if (node.type !== 'table') return null;
    const rows = node.content || [];
    const hasHeader = rows[0]?.content?.every((cell) => cell.type === 'tableHeader');
    const header = hasHeader ? rows[0] : null;
    const minRows = hasHeader ? 2 : 1;
    let best = 0;
    let low = minRows;
    let high = rows.length - 1;
    while (low <= high) {
        const middle = Math.floor((low + high) / 2);
        if (await fits(appended(prefix, tableFragment(node, rows.slice(0, middle))))) {
            best = middle;
            low = middle + 1;
        } else high = middle - 1;
    }
    if (best) return [
        tableFragment(node, rows.slice(0, best)),
        tableFragment(node, [...(header ? [clone(header)] : []), ...rows.slice(best)]),
    ];

    const rowIndex = hasHeader ? 1 : 0;
    const target = rows[rowIndex];
    if (target) {
        const pieces = await splitOversizedTableRow(target, prefix, header);
        if (pieces) return [
            tableFragment(node, [...(header ? [clone(header)] : []), pieces[0]]),
            tableFragment(node, [...(header ? [clone(header)] : []), pieces[1], ...rows.slice(rowIndex + 1)]),
        ];
    }
    if (hasHeader) {
        const headerPieces = await splitOversizedTableRow(header, prefix);
        if (headerPieces) return [
            tableFragment(node, [headerPieces[0]]),
            tableFragment(node, [headerPieces[1], ...rows.slice(1)]),
        ];
    }
    return null;
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
        ?? await splitOversizedList(node, prefix)
        ?? await splitOversizedTable(node, prefix);
}

async function paginate() {
    const thisRun = ++runId;
    emit('ready', false);

    emit('error', null);
    try {
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
        const candidate = appended(current, node);

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
            if (node.type === 'table' && current.at(-1)?.reportTableId !== node.reportTableId) {
                const splitHere = await splitOversizedTable(node, current);
                if (splitHere) {
                    result.push({ type: 'doc', content: [...current, splitHere[0]] });
                    current = [];
                    queue.unshift(splitHere[1]);
                    continue;
                }
            }
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

        if (node.type === 'table' || node.type === 'image') {
            throw new Error('Uma imagem ou tabela dos Aspectos Gerais não cabe na página A4.');
        }
        // A single indivisible legacy list item receives its own page.
        result.push({ type: 'doc', content: [node] });
    }

    if (current.length) result.push({ type: 'doc', content: current });
    if (thisRun !== runId) return;

    emit('pages', result);
    emit('ready', true);
    } catch (error) {
        if (thisRun !== runId) return;
        emit('pages', []);
        emit('error', error.message || 'Não foi possível paginar os Aspectos Gerais.');
    }
}

watch(() => [props.document, props.images], paginate, { deep: true });
onMounted(paginate);
</script>

<template>
    <div class="report-general-aspects-measure" aria-hidden="true">
        <div ref="probe" class="report-general-aspects-probe">
            <GeneralAspectsDocument :document="probeDocument" :images="images" />
        </div>
    </div>
</template>

<style>
.report-general-aspects-measure { position: fixed; left: -10000px; top: 0; width: 177mm; visibility: hidden; pointer-events: none; }
.report-general-aspects-probe { width: 177mm; height: 237mm; overflow: auto; color: #111827; font-family: Georgia, 'Times New Roman', serif; font-size: 12pt; }
.report-general-aspects-probe .general-aspects-image img { max-height: 225mm; object-fit: contain; }
</style>
