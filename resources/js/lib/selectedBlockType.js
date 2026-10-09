import { TextSelection } from '@tiptap/pm/state';

// Block types apply to whole paragraphs. Split at the selection edges first so
// a title made from part of a paragraph does not include the surrounding text.
export function selectedBlockTypeTransaction(state, typeName) {
    const { selection, schema } = state;
    if (!(selection instanceof TextSelection) || selection.empty) return null;

    const type = schema.nodes[typeName];
    if (!type || !['paragraph', 'heading'].includes(typeName)) return null;

    const { from, to } = selection;
    const tr = state.tr;

    for (const position of [to, from]) {
        const $position = tr.doc.resolve(position);
        if ($position.depth !== 1 || !['paragraph', 'heading'].includes($position.parent.type.name)
            || $position.parentOffset === 0 || $position.parentOffset === $position.parent.content.size) continue;
        tr.split(position);
    }

    const selectedFrom = tr.mapping.map(from, 1);
    const selectedTo = tr.mapping.map(to, -1);
    let changed = false;
    tr.doc.forEach((node, position) => {
        if (!['paragraph', 'heading'].includes(node.type.name)
            || position + 1 >= selectedTo || position + node.nodeSize - 1 <= selectedFrom) return;

        tr.setNodeMarkup(position, type, typeName === 'heading'
            ? { ...node.attrs, level: 1 }
            : node.attrs);
        changed = true;
    });
    if (!changed) return null;
    tr.setSelection(TextSelection.create(tr.doc, selectedFrom, selectedTo));

    return tr;
}
