import assert from 'node:assert/strict';
import test from 'node:test';
import { reportMapObservation } from '../../resources/js/lib/reportMapObservation.js';

test('map observations keep their own comment and append the current photo interval', () => {
    assert.equal(reportMapObservation({
        observations: 'Corrosão na face norte.',
        description: 'Descrição não usada',
        damage_rows: [{ photo_interval: '1 E 2' }],
    }), 'Corrosão na face norte.\nFOTOS: 1 E 2');
});

test('long map observations retain the photo line after all paragraphs for pagination', () => {
    const comment = `Primeiro parágrafo.\n${'Extensão da avaria. '.repeat(300)}`;

    assert.equal(reportMapObservation({
        observations: comment,
        damage_rows: [{ photo_interval: '5 A 8' }],
    }), `${comment}\nFOTOS: 5 A 8`);
});
