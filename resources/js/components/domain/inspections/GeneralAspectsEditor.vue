<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { Extension, Mark } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { plainTextAsHtml } from '@/lib/plainTextPaste';

const props = defineProps({
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

const PENDING_TEXT_COLOR = '#DC2626';

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
            getAttrs: (element) => {
                const color = element.style.color.replaceAll(' ', '').toUpperCase();

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
                lineHeight: {
                    default: 1.15,
                    renderHTML: ({ lineHeight }) => ({ style: `line-height: ${lineHeight}` }),
                    parseHTML: (element) => Number(element.style.lineHeight) || 1.15,
                },
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
        }];
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
    ],
    editorProps: {
        attributes: {
            class: 'general-aspects-editor-content',
            spellcheck: 'true',
        },
        handlePaste: (_view, event) => {
            const text = event.clipboardData?.getData('text/plain');
            if (text === undefined || text === '') return false;

            // Do not let HTML copied from Word, Outlook, PDFs, or browser extensions
            // reach the editor. Only our minimal HTML representation is inserted.
            return editor.value?.commands.insertContent(plainTextAsHtml(text)) ?? false;
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

const blockType = computed(() => {
    if (editor.value?.isActive('heading')) return 'heading';
    return 'paragraph';
});

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
        .updateAttributes('paragraph', { textAlign: 'left', lineHeight: 1.15, spaceBefore: 0, spaceAfter: 0, indent: 0 })
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
            <select :value="blockType" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs" title="Tipo de parágrafo" @change="setBlockType">
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
            <button type="button" class="editor-tool" :class="{ active: editor.isActive('orderedList') }" title="Lista numerada" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
            <button type="button" class="editor-tool" :class="{ active: editor.isActive('bulletList') }" title="Lista com marcadores" @click="editor.chain().focus().toggleBulletList().run()">•</button>
            <button type="button" class="editor-tool" title="Diminuir recuo" @click="adjustIndent(-1)">←</button>
            <button type="button" class="editor-tool" title="Aumentar recuo" @click="adjustIndent(1)">→</button>

            <span class="mx-0.5 h-6 w-px bg-slate-300"></span>
            <button v-for="alignment in ['left', 'center', 'right', 'justify']" :key="alignment" type="button" class="editor-tool" :class="{ active: editor.isActive({ textAlign: alignment }) }" :title="`Alinhar: ${alignment}`" @click="editor.chain().focus().setTextAlign(alignment).run()">
                {{ { left: '≡←', center: '≡', right: '→≡', justify: '☰' }[alignment] }}
            </button>

            <label class="editor-select-label">Entrelinha
                <select :value="currentLayoutAttribute('lineHeight', 1.15)" @change="setLayoutAttribute('lineHeight', $event.target.value)">
                    <option v-for="value in [1, 1.15, 1.5, 2]" :key="value" :value="value">{{ String(value).replace('.', ',') }}</option>
                </select>
            </label>
            <label class="editor-select-label">Antes
                <select :value="currentLayoutAttribute('spaceBefore', 0)" @change="setLayoutAttribute('spaceBefore', $event.target.value)">
                    <option v-for="value in [0, 4, 8, 12]" :key="value" :value="value">{{ value }} pt</option>
                </select>
            </label>
            <label class="editor-select-label">Depois
                <select :value="currentLayoutAttribute('spaceAfter', 0)" @change="setLayoutAttribute('spaceAfter', $event.target.value)">
                    <option v-for="value in [0, 4, 8, 12]" :key="value" :value="value">{{ value }} pt</option>
                </select>
            </label>

            <span class="mx-0.5 h-6 w-px bg-slate-300"></span>
            <button type="button" class="editor-tool" title="Desfazer" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">↶</button>
            <button type="button" class="editor-tool" title="Refazer" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">↷</button>
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100" @click="clearFormatting">Limpar formatação</button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.editor-tool { display: inline-flex; min-width: 2rem; height: 2rem; align-items: center; justify-content: center; border: 1px solid #cbd5e1; border-radius: .5rem; background: white; color: #334155; font-size: .75rem; }
.editor-tool:hover, .editor-tool.active { border-color: #0f766e; background: #ccfbf1; color: #115e59; }
.editor-tool:disabled { cursor: not-allowed; opacity: .4; }
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
.general-aspects-editor-content p { min-height: 1.15em; }
.general-aspects-editor-content h1 { font-size: 1.45em; font-weight: 800; }
.general-aspects-editor-content h2 { font-size: 1.25em; font-weight: 750; }
.general-aspects-editor-content h3 { font-size: 1.1em; font-weight: 700; }
.general-aspects-editor-content ul { margin: 4pt 0 4pt 7mm; padding-left: 5mm; list-style: disc; }
.general-aspects-editor-content ol { margin: 4pt 0 4pt 7mm; padding-left: 5mm; list-style: decimal; }
.general-aspects-editor-content li { padding-left: 1.5mm; }
.general-aspects-editor-content .is-editor-empty:first-child::before { float: left; height: 0; color: #94a3b8; content: 'Descreva os aspectos gerais do equipamento…'; pointer-events: none; }
</style>
