import assert from 'node:assert/strict';
import test from 'node:test';
import { plainTextAsHtml } from '../../resources/js/lib/plainTextPaste.js';

test('converts plain text line breaks into editor paragraphs and hard breaks', () => {
    assert.equal(
        plainTextAsHtml('Primeira linha\r\nSegunda linha\n\nSegundo parágrafo'),
        '<p>Primeira linha<br>Segunda linha</p><p>Segundo parágrafo</p>',
    );
});

test('escapes clipboard HTML before it is inserted into the editor', () => {
    assert.equal(
        plainTextAsHtml('<strong>Texto</strong> & "aspas"'),
        '<p>&lt;strong&gt;Texto&lt;/strong&gt; &amp; &quot;aspas&quot;</p>',
    );
});
