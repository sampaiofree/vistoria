export class ReportQuantityRowOverflowError extends Error {
    constructor(index) {
        super(`A linha ${index + 1} do quantitativo excede a área útil da página.`);
        this.name = 'ReportQuantityRowOverflowError';
        this.index = index;
    }
}

export async function paginateQuantityRows(items, fits, isCurrent = () => true) {
    const pages = [];
    let current = [];

    for (const [index, item] of items.entries()) {
        if (!isCurrent()) return null;

        const candidate = [...current, item];
        if (await fits(candidate, pages.length > 0)) {
            current = candidate;
            continue;
        }

        if (current.length) {
            if (!await fits([item], true)) throw new ReportQuantityRowOverflowError(index);
            pages.push(current);
            current = [item];
        } else {
            throw new ReportQuantityRowOverflowError(index);
        }
    }

    if (!isCurrent()) return null;
    if (current.length) pages.push(current);

    return pages;
}
