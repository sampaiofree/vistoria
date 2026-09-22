export const CATALOG_SEARCH_THRESHOLD = 8;

export function normalizeCatalogSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase('pt-BR')
        .trim()
        .replace(/\s+/g, ' ');
}

function optionSearchText(option) {
    const score = option?.score;

    return normalizeCatalogSearch([
        option?.label,
        option?.description,
        option?.name,
        option?.code,
        option?.value,
        score,
        score === null || score === undefined ? null : `nota ${score}`,
    ].filter((value) => value !== null && value !== undefined).join(' '));
}

export function filterCatalogOptions(options, query) {
    const terms = normalizeCatalogSearch(query).split(' ').filter(Boolean);
    if (terms.length === 0) return options ?? [];

    return (options ?? []).filter((option) => {
        const searchable = optionSearchText(option);

        return terms.every((term) => searchable.includes(term));
    });
}

export function catalogOptionsAreSearchable(options, threshold = CATALOG_SEARCH_THRESHOLD) {
    return (options?.length ?? 0) >= threshold;
}
