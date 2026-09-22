import assert from 'node:assert/strict';
import test from 'node:test';
import {
    catalogOptionsAreSearchable,
    filterCatalogOptions,
    normalizeCatalogSearch,
} from '../../resources/js/lib/catalogOptionSearch.js';

const options = [
    { code: 'connection_plate', label: 'Talas de ligação', score: 5 },
    { code: 'secondary_beam', label: 'Viga secundária', score: 3 },
    { code: 'guardrail', label: 'Guarda-corpo', score: 2 },
];

test('normalizes accents, case and repeated whitespace', () => {
    assert.equal(normalizeCatalogSearch('  LIGAÇÃO   Secundária '), 'ligacao secundaria');
});

test('filters catalog options by label, code and score', () => {
    assert.deepEqual(filterCatalogOptions(options, 'LIGACAO'), [options[0]]);
    assert.deepEqual(filterCatalogOptions(options, 'secondary_beam'), [options[1]]);
    assert.deepEqual(filterCatalogOptions(options, 'nota 5'), [options[0]]);
    assert.deepEqual(filterCatalogOptions(options, 'viga 3'), [options[1]]);
});

test('returns all options for an empty search and none when there is no match', () => {
    assert.deepEqual(filterCatalogOptions(options, ''), options);
    assert.deepEqual(filterCatalogOptions(options, 'chaminé'), []);
});

test('enables search only at the configured option threshold', () => {
    assert.equal(catalogOptionsAreSearchable(Array.from({ length: 7 })), false);
    assert.equal(catalogOptionsAreSearchable(Array.from({ length: 8 })), true);
    assert.equal(catalogOptionsAreSearchable(Array.from({ length: 3 }), 3), true);
});
