import test from 'node:test';
import assert from 'node:assert/strict';
import { Schema } from '@tiptap/pm/model';
import { EditorState, TextSelection } from '@tiptap/pm/state';
import { selectedBlockTypeTransaction } from '../../resources/js/lib/selectedBlockType.js';

const schema = new Schema({
    nodes: {
        doc: { content: 'block+' },
        paragraph: { group: 'block', content: 'inline*', attrs: { textAlign: { default: 'left' } } },
        heading: { group: 'block', content: 'inline*', attrs: { level: { default: 1 }, textAlign: { default: 'left' } } },
        text: { group: 'inline' },
    },
});

function stateWithSelection(blocks, from, to) {
    const doc = schema.node('doc', null, blocks.map(({ type = 'paragraph', text, attrs }) =>
        schema.node(type, attrs, text ? [schema.text(text)] : [])));
    return EditorState.create({ doc, selection: TextSelection.create(doc, from, to) });
}

function blocksOf(state, type) {
    const tr = selectedBlockTypeTransaction(state, type);
    assert.ok(tr);
    return {
        blocks: tr.doc.toJSON().content.map((block) => ({ type: block.type, text: block.content?.[0]?.text ?? '', attrs: block.attrs })),
        selection: tr.selection.content().content.textBetween(0, tr.selection.content().content.size),
    };
}

test('turns only a selected phrase within a paragraph into a heading', () => {
    const state = stateWithSelection([{ text: 'Antes Título Depois', attrs: { textAlign: 'center' } }], 7, 13);
    const result = blocksOf(state, 'heading');

    assert.deepEqual(result.blocks.map(({ type, text }) => [type, text]), [
        ['paragraph', 'Antes '], ['heading', 'Título'], ['paragraph', ' Depois'],
    ]);
    assert.equal(result.blocks[1].attrs.textAlign, 'center');
    assert.equal(result.selection, 'Título');
});

test('keeps the unselected ends of multiple paragraphs unchanged', () => {
    const state = stateWithSelection([{ text: 'Antes Um' }, { text: 'Dois Depois' }], 7, 15);
    const result = blocksOf(state, 'heading');

    assert.deepEqual(result.blocks.map(({ type, text }) => [type, text]), [
        ['paragraph', 'Antes '], ['heading', 'Um'], ['heading', 'Dois'], ['paragraph', ' Depois'],
    ]);
});

test('does not include a following paragraph when selection ends at its start', () => {
    const state = stateWithSelection([{ text: 'Primeiro' }, { text: 'Segundo' }], 1, 11);
    const result = blocksOf(state, 'heading');

    assert.deepEqual(result.blocks.map(({ type, text }) => [type, text]), [
        ['heading', 'Primeiro'], ['paragraph', 'Segundo'],
    ]);
});

test('converts only the selected part of a heading back to a paragraph', () => {
    const state = stateWithSelection([{ type: 'heading', text: 'Antes Título Depois' }], 7, 13);
    const result = blocksOf(state, 'paragraph');

    assert.deepEqual(result.blocks.map(({ type, text }) => [type, text]), [
        ['heading', 'Antes '], ['paragraph', 'Título'], ['heading', ' Depois'],
    ]);
});

test('leaves an empty selection to the editor’s normal block command', () => {
    assert.equal(selectedBlockTypeTransaction(stateWithSelection([{ text: 'Texto' }], 2, 2), 'heading'), null);
});
