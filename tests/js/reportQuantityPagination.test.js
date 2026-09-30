import assert from 'node:assert/strict';
import test from 'node:test';
import { paginateQuantityRows, ReportQuantityRowOverflowError } from '../../resources/js/lib/reportQuantityPagination.js';

test('splits long quantitative tables at measured page capacity without losing rows', async () => {
    const rows = Array.from({ length: 35 }, (_, index) => ({ id: index + 1 }));
    const measured = [];
    const pages = await paginateQuantityRows(rows, async (candidate, continuation) => {
        measured.push({ length: candidate.length, continuation });
        return candidate.length <= (continuation ? 12 : 11);
    });

    assert.deepEqual(pages.map((page) => page.length), [11, 12, 12]);
    assert.deepEqual(pages.flat().map((row) => row.id), rows.map((row) => row.id));
    assert.ok(measured.some((measurement) => measurement.continuation));
});

test('cancels obsolete measurements when quantitative rows change', async () => {
    let current = true;
    const pages = await paginateQuantityRows([1, 2, 3], async () => {
        current = false;
        return true;
    }, () => current);

    assert.equal(pages, null);
});

test('blocks export if even one quantitative row exceeds a page', async () => {
    await assert.rejects(
        paginateQuantityRows(['short', 'long'], async (candidate) => candidate.length === 1 && candidate[0] === 'short'),
        (error) => error instanceof ReportQuantityRowOverflowError && error.index === 1,
    );
});
