<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Extension, Mark, Node } from '@tiptap/core';
import { Slice } from '@tiptap/pm/model';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { TableKit } from '@tiptap/extension-table';
import { Plugin } from '@tiptap/pm/state';
import { plainTextAsHtml } from '@/lib/plainTextPaste';
import UiIcon from '@/components/ui/UiIcon.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    equipmentFields: { type: Array, default: () => [] },
    enableMedia: { type: Boolean, default: false },
    imageAssets: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue', 'image:selected', 'media:error']);
const imageInput = ref(null);

const PENDING_TEXT_COLOR = '#DC2626';
const FIELD_CLIPBOARD_TYPE = 'application/x-vistoria-equipment-fields+json';
const fieldsOpen = ref(false);
const fieldSearch = ref('');
const highlightedField = ref(0);
const fieldSearchInput = ref(null);
const fieldPicker = ref(null);
const fieldLabels = computed(() => Object.fromEntries(props.equipmentFields.map(({ key, label }) => [key, label])));
const filteredFields = computed(() => {
    const query = normalizeSearch(fieldSearch.value);
    return props.equipmentFields.filter(({ label }) => normalizeSearch(label).includes(query));
});

function normalizeSearch(text) {
    return String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
}

const EquipmentField = Node.create({
    name: 'equipmentField',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    addAttributes() {
        return {
            key: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-equipment-field'),
                renderHTML: ({ key }) => ({ 'data-equipment-field': key }),
            },
        };
    },
    parseHTML() { return [{ tag: 'span[data-equipment-field]' }]; },
    renderHTML({ node, HTMLAttributes }) {
        return ['span', {
            ...HTMLAttributes,
            class: 'general-aspects-equipment-field',
            contenteditable: 'false',
        }, `[${fieldLabels.value[node.attrs.key] ?? node.attrs.key}]`];
    },
});

const PendingTextColor = Mark.create({
    name: 'textColor',
    addAttributes() {
        return {
            color: {
                default: null,
                renderHTML: (attributes) => attributes.color === PENDING_TEXT_COLOR
                    ? { style: `color: ${PENDING_TEXT_COLOR}` }
                    : {},
            },
        };
    },
    parseHTML() {
        return [{
            style: 'color',
            getAttrs: (value) => {
                const color = value.replaceAll(' ', '').toUpperCase();

                return ['#DC2626', 'RGB(220,38,38)'].includes(color)
                    ? { color: PENDING_TEXT_COLOR }
                    : false;
            },
        }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', HTMLAttributes, 0];
    },
});

const LayoutAttributes = Extension.create({
    name: 'generalAspectsLayout',
    addGlobalAttributes() {
        return [{
            types: ['paragraph', 'heading'],
            attributes: {
                spaceBefore: {
                    default: 0,
                    renderHTML: ({ spaceBefore }) => ({ style: `margin-top: ${spaceBefore}pt` }),
                    parseHTML: (element) => Number.parseInt(element.style.marginTop, 10) || 0,
                },
                spaceAfter: {
                    default: 0,
                    renderHTML: ({ spaceAfter }) => ({ style: `margin-bottom: ${spaceAfter}pt` }),
                    parseHTML: (element) => Number.parseInt(element.style.marginBottom, 10) || 0,
                },
                indent: {
                    default: 0,
                    renderHTML: ({ indent }) => ({ style: `margin-left: ${indent}mm` }),
                    parseHTML: (element) => Number.parseInt(element.style.marginLeft, 10) || 0,
                },
            },
        }, ...[
            { type: 'paragraph', lineHeight: 1.5 },
            { type: 'heading', lineHeight: 1.15 },
        ].map(({ type, lineHeight }) => ({
            types: [type],
            attributes: {
                lineHeight: {
                    default: lineHeight,
                    renderHTML: ({ lineHeight: value }) => ({ style: `line-height: ${value}` }),
                    parseHTML: (element) => Number(element.style.lineHeight) || lineHeight,
                },
            },
        }))];
    },
});

const GeneralAspectImage = Node.create({
    name: 'image', group: 'block', atom: true, selectable: true, draggable: false,
    addAttributes() {
        return { assetId: { default: null, parseHTML: (element) => element.getAttribute('data-asset-id') } };
    },
    parseHTML() { return [{ tag: 'figure[data-asset-id]' }]; },
    renderHTML({ node }) {
        const asset = props.imageAssets[node.attrs.assetId];
        return ['figure', { 'data-asset-id': node.attrs.assetId, class: 'general-aspects-image-block' },
            ['img', { ...(asset?.thumbnail ? { src: asset.thumbnail } : {}), alt: '', draggable: 'false' }],
            ...(asset?.status === 'failed' ? [['span', {}, 'Falha na imagem. Remova ou substitua.']] : [])];
    },
});

function validTable(node) {
    if (node.type.name !== 'table') return true;
    if (node.childCount < 1 || node.childCount > 50) return false;
    const cols = node.firstChild.childCount;
    if (cols < 1 || cols > 6) return false;
    for (let rowIndex = 0; rowIndex < node.childCount; rowIndex++) {
        const row = node.child(rowIndex);
        if (row.type.name !== 'tableRow' || row.childCount !== cols) return false;
        for (let col = 0; col < cols; col++) {
            const cell = row.child(col);
            const paragraph = cell.firstChild;
            if (!['tableCell', 'tableHeader'].includes(cell.type.name) || (rowIndex > 0 && cell.type.name === 'tableHeader')
                || cell.attrs.colspan !== 1 || cell.attrs.rowspan !== 1 || cell.attrs.colwidth !== null || cell.attrs.align !== null
                || cell.childCount !== 1 || paragraph.type.name !== 'paragraph'
                || ![null, 'left'].includes(paragraph.attrs.textAlign)
                || paragraph.attrs.lineHeight !== 1.5 || paragraph.attrs.spaceBefore !== 0
                || paragraph.attrs.spaceAfter !== 0 || paragraph.attrs.indent !== 0) return false;
            let allowed = true;
            paragraph.descendants((child) => {
                if (!['text', 'hardBreak', ...(props.equipmentFields.length ? ['equipmentField'] : [])].includes(child.type.name)
                    || child.marks.some((mark) => !['bold', 'italic', 'textColor'].includes(mark.type.name))) allowed = false;
            });
            if (!allowed) return false;
        }
    }
    return true;
}

const InspectionMediaLimits = Extension.create({
    name: 'inspectionMediaLimits',
    addProseMirrorPlugins() {
        return [new Plugin({
            filterTransaction(transaction) {
                if (!transaction.docChanged) return true;
                let images = 0;
                let valid = true;
                transaction.doc.descendants((node, _pos, parent) => {
                    if (node.type.name === 'image') images++;
                    if (node.type.name === 'table' && !validTable(node)) valid = false;
                    if (['image', 'table'].includes(node.type.name) && parent?.type.name !== 'doc') valid = false;
                });
                if (images > 10) valid = false;
                if (!valid) emit('media:error', 'Até 10 imagens; tabelas de até 6 colunas e 50 linhas, sem mesclagem.');
                return valid;
            },
        })];
    },
});

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            blockquote: false,
            code: false,
            codeBlock: false,
            horizontalRule: false,
            link: false,
            strike: false,
            underline: false,
        }),
        TextAlign.configure({ types: ['heading', 'paragraph'], alignments: ['left', 'center', 'right', 'justify'] }),
        LayoutAttributes,
        PendingTextColor,
        ...(props.equipmentFields.length ? [EquipmentField] : []),
        ...(props.enableMedia ? [GeneralAspectImage, TableKit.configure({ table: { resizable: false }, tableCell: {}, tableHeader: {}, tableRow: {} }), InspectionMediaLimits] : []),
    ],
    editorProps: {
        attributes: {
            class: 'general-aspects-editor-content',
            spellcheck: 'true',
        },
        handlePaste: (_view, event) => {
            if (props.enableMedia) {
                const image = Array.from(event.clipboardData?.files || []).find((file) => file.type.startsWith('image/'));
                if (image) {
                    emit('image:selected', image);
                    return true;
                }
            }
            const fieldSlice = event.clipboardData?.getData(FIELD_CLIPBOARD_TYPE);
            if (props.equipmentFields.length && fieldSlice) {
                try {
                    const slice = Slice.fromJSON(_view.state.schema, JSON.parse(fieldSlice));
                    _view.dispatch(_view.state.tr.replaceSelection(slice).scrollIntoView());
                    return true;
                } catch (_error) {
                    // Unknown clipboard data is pasted as plain text below.
                }
            }
            const text = event.clipboardData?.getData('text/plain');
            if (text === undefined || text === '') return false;

            if (props.enableMedia && editor.value?.isActive('table')) {
                const lines = text.split(/\r?\n/);
                return editor.value.commands.insertContent(lines.flatMap((line, index) => [
                    ...(index ? [{ type: 'hardBreak' }] : []),
                    ...(line ? [{ type: 'text', text: line }] : []),
                ]));
            }

            // Do not let HTML copied from Word, Outlook, PDFs, or browser extensions
            // reach the editor. Only our minimal HTML representation is inserted.
            return editor.value?.commands.insertContent(plainTextAsHtml(text)) ?? false;
        },
        handleDOMEvents: {
            copy: (view, event) => copyFieldSelection(view, event, false),
            cut: (view, event) => copyFieldSelection(view, event, true),
        },
    },
    onUpdate: ({ editor: currentEditor }) => emit('update:modelValue', currentEditor.getJSON()),
});

onBeforeUnmount(() => editor.value?.destroy());

watch(
    () => props.modelValue,
    (document) => {
        const currentEditor = editor.value;
        if (!currentEditor || JSON.stringify(currentEditor.getJSON()) === JSON.stringify(document)) return;

        currentEditor.commands.setContent(document, { emitUpdate: false });
    },
    { deep: true },
);

watch(() => props.imageAssets, async () => {
    if (!props.enableMedia || !editor.value) return;
    await nextTick();
    editor.value.view.dom.querySelectorAll('figure[data-asset-id]').forEach((figure) => {
        const asset = props.imageAssets[figure.getAttribute('data-asset-id')];
        const image = figure.querySelector('img');
        if (!image) return;
        if (asset?.thumbnail) image.setAttribute('src', asset.thumbnail);
        else image.removeAttribute('src');
    });
}, { deep: true });

function onImageInput(event) {
    const file = event.target.files?.[0];
    if (file) emit('image:selected', file);
    event.target.value = '';
}

function insertImage(assetId) {
    const current = editor.value;
    if (!current) return false;
    const selection = current.state.selection.$from;
    const nested = selection.depth > 1 && ['table', 'bulletList', 'orderedList'].includes(selection.node(1).type.name);
    const chain = current.chain().focus();
    const inserted = nested
        ? chain.insertContentAt(selection.after(1), { type: 'image', attrs: { assetId } }).run()
        : chain.insertContent({ type: 'image', attrs: { assetId } }).run();
    if (!inserted) emit('media:error', 'Não foi possível inserir a imagem. Selecione outro ponto do texto e tente novamente.');
    return inserted;
}
defineExpose({ insertImage });

const inTable = computed(() => editor.value?.isActive('table') ?? false);
function tableSize() {
    const selection = editor.value?.state.selection.$from;
    if (!selection) return { rows: 0, cols: 0 };
    for (let depth = selection.depth; depth >= 0; depth--) {
        if (selection.node(depth).type.name === 'table') {
            const table = selection.node(depth);
            return { rows: table.childCount, cols: table.firstChild?.childCount || 0 };
        }
    }
    return { rows: 0, cols: 0 };
}
const tableRows = computed(() => tableSize().rows);
const tableColumns = computed(() => tableSize().cols);

const blockType = computed(() => {
    if (editor.value?.isActive('heading')) return 'heading';
    return 'paragraph';
});

function copyFieldSelection(view, event, cut) {
    if (!props.equipmentFields.length || view.state.selection.empty || !event.clipboardData) return false;
    const slice = view.state.selection.content();
    const serialized = view.serializeForClipboard(slice);
    event.preventDefault();
    event.clipboardData.clearData();
    event.clipboardData.setData('text/html', serialized.dom.innerHTML);
    event.clipboardData.setData('text/plain', serialized.text);
    event.clipboardData.setData(FIELD_CLIPBOARD_TYPE, JSON.stringify(slice.toJSON()));
    if (cut) view.dispatch(view.state.tr.deleteSelection().scrollIntoView());
    return true;
}

async function openFields() {
    fieldsOpen.value = !fieldsOpen.value;
    fieldSearch.value = '';
    highlightedField.value = 0;
    if (fieldsOpen.value) {
        await nextTick();
        fieldSearchInput.value?.focus();
    }
}

function insertField(field) {
    editor.value?.chain().focus().insertContent({ type: 'equipmentField', attrs: { key: field.key } }).run();
    fieldsOpen.value = false;
}

function onFieldSearchKeydown(event) {
    if (event.key === 'Escape') {
        fieldsOpen.value = false;
        editor.value?.commands.focus();
    } else if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedField.value = Math.min(filteredFields.value.length - 1, highlightedField.value + 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedField.value = Math.max(0, highlightedField.value - 1);
    } else if (event.key === 'Enter' && filteredFields.value[highlightedField.value]) {
        event.preventDefault();
        insertField(filteredFields.value[highlightedField.value]);
    }
}

function onFieldPickerFocusout(event) {
    if (!fieldPicker.value?.contains(event.relatedTarget)) fieldsOpen.value = false;
}

watch(fieldSearch, () => { highlightedField.value = 0; });

function setBlockType(event) {
    const value = event.target.value;
    const chain = editor.value?.chain().focus();
    if (!chain) return;

    if (value === 'paragraph') chain.setParagraph().run();
    else chain.setHeading({ level: 1 }).run();
}

function currentLayoutAttribute(name, fallback) {
    const currentEditor = editor.value;
    if (!currentEditor) return fallback;

    return currentEditor.getAttributes(currentEditor.isActive('heading') ? 'heading' : 'paragraph')[name] ?? fallback;
}

function setLayoutAttribute(name, value) {
    const currentEditor = editor.value;
    if (!currentEditor) return;

    const parsed = name === 'lineHeight' ? Number(value) : Number.parseInt(value, 10);
    currentEditor.chain().focus()
        .updateAttributes('paragraph', { [name]: parsed })
        .updateAttributes('heading', { [name]: parsed })
        .run();
}

function adjustIndent(direction) {
    const currentEditor = editor.value;
    if (!currentEditor) return;

    if (currentEditor.isActive('listItem')) {
        const chain = currentEditor.chain().focus();
        (direction > 0 ? chain.sinkListItem('listItem') : chain.liftListItem('listItem')).run();
        return;
    }

    const values = [0, 10, 20, 30];
    const current = Number(currentLayoutAttribute('indent', 0));
    const index = Math.max(0, values.indexOf(current));
    setLayoutAttribute('indent', values[Math.min(values.length - 1, Math.max(0, index + direction))]);
}

function clearFormatting() {
    editor.value?.chain().focus()
        .unsetAllMarks()
        .clearNodes()
        .updateAttributes('paragraph', { textAlign: 'left', lineHeight: 1.5, spaceBefore: 0, spaceAfter: 0, indent: 0 })
        .run();
}

function setPendingTextColor() {
    editor.value?.chain().focus().setMark('textColor', { color: PENDING_TEXT_COLOR }).run();
}

function clearTextColor() {
    editor.value?.chain().focus().unsetMark('textColor').run();
}

function hasPendingTextColor() {
    return editor.value?.isActive('textColor', { color: PENDING_TEXT_COLOR }) ?? false;
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white">
        <div v-if="editor" class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 bg-slate-50 p-2" role="toolbar" aria-label="Formatação dos aspectos gerais">
            <select :value="blockType" :disabled="inTable" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs" title="Tipo de parágrafo" @change="setBlockType">
                <option value="paragraph">Parágrafo</option>
                <option value="heading">Título</option>
            </select>
            <button type="button" class="editor-tool" :class="{ active: editor.isActive('bold') }" title="Negrito" @click="editor.chain().focus().toggleBold().run()"><strong>N</strong></button>
            <button type="button" class="editor-tool" :class="{ active: editor.isActive('italic') }" title="Itálico" @click="editor.chain().focus().toggleItalic().run()"><em>I</em></button>
            <div class="editor-color-controls" role="group" aria-label="Cor do texto">
                <span class="editor-color-label">Cor:</span>
                <button
                    type="button"
                    class="editor-color-tool editor-color-tool-default"
                    :class="{ active: !hasPendingTextColor() }"
                    title="Aplicar cor padrão ao texto selecionado"
                    aria-label="Aplicar cor padrão ao texto selecionado"
                    @click="clearTextColor"
                >
                    <span class="editor-color-letter">A</span>
                    <span>Padrão</span>
                </button>
                <button
                    type="button"
                    class="editor-color-tool editor-color-tool-red"
                    :class="{ active: hasPendingTextColor() }"
                    title="Destacar o texto selecionado em vermelho"
                    aria-label="Destacar o texto selecionado em vermelho"
                    @click="setPendingTextColor"
                >
                    <span class="editor-color-letter">A</span>
                    <span>Vermelho</span>
                </button>
            </div>
            <button v-if="!inTable" type="button" class="editor-tool" :class="{ active: editor.isActive('orderedList') }" title="Lista numerada" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
            <button v-if="!inTable" type="button" class="editor-tool" :class="{ active: editor.isActive('bulletList') }" title="Lista com marcadores" @click="editor.chain().focus().toggleBulletList().run()">•</button>
            <button v-if="!inTable" type="button" class="editor-tool" title="Diminuir recuo" @click="adjustIndent(-1)">←</button>
            <button v-if="!inTable" type="button" class="editor-tool" title="Aumentar recuo" @click="adjustIndent(1)">→</button>
            <template v-if="enableMedia">
                <span class="mx-0.5 h-6 w-px bg-slate-300"></span>
                <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onImageInput">
                <button type="button" class="editor-tool" title="Inserir imagem" aria-label="Inserir imagem" @click="imageInput?.click()"><UiIcon name="photos" class="h-4 w-4" /></button>
                <button type="button" class="editor-tool" title="Inserir tabela" aria-label="Inserir tabela" @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()"><UiIcon name="table" class="h-4 w-4" /></button>
                <template v-if="inTable">
                    <button type="button" class="editor-tool" title="Adicionar linha" aria-label="Adicionar linha" :disabled="tableRows >= 50" @click="editor.chain().focus().addRowAfter().run()"><UiIcon name="table-row-add" class="h-4 w-4" /></button>
                    <button type="button" class="editor-tool" title="Remover linha" aria-label="Remover linha" @click="editor.chain().focus().deleteRow().run()"><UiIcon name="table-row-remove" class="h-4 w-4" /></button>
                    <button type="button" class="editor-tool" title="Adicionar coluna" aria-label="Adicionar coluna" :disabled="tableColumns >= 6" @click="editor.chain().focus().addColumnAfter().run()"><UiIcon name="table-column-add" class="h-4 w-4" /></button>
                    <button type="button" class="editor-tool" title="Remover coluna" aria-label="Remover coluna" @click="editor.chain().focus().deleteColumn().run()"><UiIcon name="table-column-remove" class="h-4 w-4" /></button>
                    <button type="button" class="editor-tool" title="Ativar ou desativar cabeçalho" aria-label="Ativar ou desativar cabeçalho" @click="editor.chain().focus().toggleHeaderRow().run()"><UiIcon name="table-header" class="h-4 w-4" /></button>
                    <button type="button" class="editor-tool" title="Excluir tabela" aria-label="Excluir tabela" @click="editor.chain().focus().deleteTable().run()"><UiIcon name="trash" class="h-4 w-4" /></button>
                </template>
                <button v-if="editor.isActive('image')" type="button" class="editor-tool" title="Remover imagem selecionada" @click="editor.chain().focus().deleteSelection().run()">Remover imagem</button>
            </template>

            <span class="mx-0.5 h-6 w-px bg-slate-300"></span>
            <button v-for="alignment in (inTable ? [] : ['left', 'center', 'right', 'justify'])" :key="alignment" type="button" class="editor-tool" :class="{ active: editor.isActive({ textAlign: alignment }) }" :title="`Alinhar: ${alignment}`" @click="editor.chain().focus().setTextAlign(alignment).run()">
                {{ { left: '≡←', center: '≡', right: '→≡', justify: '☰' }[alignment] }}
            </button>

            <label v-if="!inTable" class="editor-select-label">Entrelinha
                <select :value="currentLayoutAttribute('lineHeight', blockType === 'paragraph' ? 1.5 : 1.15)" @change="setLayoutAttribute('lineHeight', $event.target.value)">
                    <option v-for="value in [1, 1.15, 1.5, 2]" :key="value" :value="value">{{ String(value).replace('.', ',') }}</option>
                </select>
            </label>
            <label v-if="!inTable" class="editor-select-label">Antes
                <select :value="currentLayoutAttribute('spaceBefore', 0)" @change="setLayoutAttribute('spaceBefore', $event.target.value)">
                    <option v-for="value in [0, 4, 8, 12]" :key="value" :value="value">{{ value }} pt</option>
                </select>
            </label>
            <label v-if="!inTable" class="editor-select-label">Depois
                <select :value="currentLayoutAttribute('spaceAfter', 0)" @change="setLayoutAttribute('spaceAfter', $event.target.value)">
                    <option v-for="value in [0, 4, 8, 12]" :key="value" :value="value">{{ value }} pt</option>
                </select>
            </label>

            <span class="mx-0.5 h-6 w-px bg-slate-300"></span>
            <button type="button" class="editor-tool" title="Desfazer" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">↶</button>
            <button type="button" class="editor-tool" title="Refazer" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">↷</button>
            <button v-if="!inTable" type="button" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100" @click="clearFormatting">Limpar formatação</button>
            <div v-if="equipmentFields.length" ref="fieldPicker" class="relative" @focusout="onFieldPickerFocusout">
                <button type="button" class="rounded-lg border border-teal-300 bg-white px-2 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-50" :aria-expanded="fieldsOpen" aria-controls="equipment-field-options" @click="openFields">Inserir campo</button>
                <div v-if="fieldsOpen" id="equipment-field-options" class="absolute left-0 top-full z-30 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                    <input ref="fieldSearchInput" v-model="fieldSearch" type="search" aria-label="Pesquisar campo do item de manutenção" placeholder="Pesquisar campo…" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @keydown="onFieldSearchKeydown">
                    <div class="mt-1 max-h-60 overflow-y-auto" role="listbox" aria-label="Campos do item de manutenção">
                        <button v-for="(field, index) in filteredFields" :key="field.key" type="button" role="option" :aria-selected="index === highlightedField" class="block w-full rounded-md px-2 py-1.5 text-left text-sm hover:bg-teal-50" :class="{ 'bg-teal-50': index === highlightedField }" @click="insertField(field)">{{ field.label }}</button>
                        <p v-if="!filteredFields.length" class="p-2 text-sm text-slate-500">Nenhum campo encontrado.</p>
                    </div>
                </div>
            </div>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.editor-tool { display: inline-flex; min-width: 2rem; height: 2rem; align-items: center; justify-content: center; border: 1px solid #cbd5e1; border-radius: .5rem; background: white; color: #334155; font-size: .75rem; }
.editor-tool:hover, .editor-tool.active { border-color: #0f766e; background: #ccfbf1; color: #115e59; }
.editor-tool:disabled { cursor: not-allowed; opacity: .4; }
.editor-tool:focus-visible { outline: 2px solid #0d9488; outline-offset: 2px; }
.editor-color-controls { display: inline-flex; align-items: center; gap: .25rem; border-right: 1px solid #cbd5e1; padding-right: .5rem; }
.editor-color-label { color: #475569; font-size: .7rem; font-weight: 700; }
.editor-color-tool { display: inline-flex; height: 2rem; align-items: center; gap: .3rem; border: 1px solid #cbd5e1; border-radius: .5rem; background: white; padding: 0 .55rem; color: #334155; font-size: .72rem; font-weight: 700; }
.editor-color-tool:hover { background: #f8fafc; }
.editor-color-letter { font-size: .9rem; font-weight: 800; line-height: 1; }
.editor-color-tool-default .editor-color-letter { color: #1e293b; text-decoration: underline; text-decoration-thickness: 2px; text-underline-offset: 2px; }
.editor-color-tool-default.active { border-color: #0f766e; background: #ccfbf1; color: #115e59; }
.editor-color-tool-red { border-color: #fca5a5; background: #fff1f2; color: #b91c1c; }
.editor-color-tool-red .editor-color-letter { color: #dc2626; }
.editor-color-tool-red:hover, .editor-color-tool-red.active { border-color: #dc2626; background: #fee2e2; color: #991b1b; }
.editor-select-label { display: inline-flex; align-items: center; gap: .25rem; color: #475569; font-size: .7rem; font-weight: 600; }
.editor-select-label select { border: 1px solid #cbd5e1; border-radius: .5rem; background: white; padding: .35rem .45rem; font-size: .75rem; }
.general-aspects-editor-content { min-height: 22rem; padding: 1rem 1.25rem; color: #1e293b; outline: none; }
.general-aspects-editor-content p { min-height: 1.5em; }
.general-aspects-editor-content h1 { font-size: 1.45em; font-weight: 800; }
.general-aspects-editor-content h2 { font-size: 1.25em; font-weight: 750; }
.general-aspects-editor-content h3 { font-size: 1.1em; font-weight: 700; }
.general-aspects-editor-content ul { margin: 4pt 0 4pt 7mm; padding-left: 5mm; list-style: disc; }
.general-aspects-editor-content ol { margin: 4pt 0 4pt 7mm; padding-left: 5mm; list-style: decimal; }
.general-aspects-editor-content li { padding-left: 1.5mm; }
.general-aspects-editor-content .is-editor-empty:first-child::before { float: left; height: 0; color: #94a3b8; content: 'Descreva os aspectos gerais do equipamento…'; pointer-events: none; }
.general-aspects-editor-content .general-aspects-equipment-field { display: inline-block; border: 1px solid #5eead4; border-radius: .4rem; background: #ccfbf1; padding: 0 .25rem; color: #115e59; font-weight: 600; white-space: normal; }
.general-aspects-editor-content .general-aspects-image-block { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 4rem; margin: .75rem 0; background: #f8fafc; }
.general-aspects-editor-content .general-aspects-image-block img { display: block; max-width: 100%; height: auto; }
.general-aspects-editor-content .general-aspects-image-block.ProseMirror-selectednode { outline: 2px solid #0d9488; }
.general-aspects-editor-content table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: .75rem 0; }
.general-aspects-editor-content th, .general-aspects-editor-content td { border: 1px solid #94a3b8; padding: .4rem; vertical-align: top; overflow-wrap: anywhere; }
.general-aspects-editor-content th { background: #f1f5f9; font-weight: 700; }
.general-aspects-editor-content .selectedCell { background: #ccfbf1; }
</style>
