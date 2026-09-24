function categoryCode(definition) {
    return definition?.category?.code ?? definition?.category ?? null;
}

function optionCode(option) {
    return option?.code ?? option?.value ?? null;
}

function findOption(options, code) {
    return (options ?? []).find((option) => optionCode(option) === code) ?? null;
}

function scoreOf(value) {
    const score = Number(value);

    return Number.isInteger(score) && score >= 1 && score <= 5 ? score : null;
}

export function trendOptionsFor(definition, groupCode) {
    if (categoryCode(definition) === 'TAC') return definition?.trend_options ?? [];

    return (definition?.trend_groups ?? [])
        .find((group) => optionCode(group) === groupCode)
        ?.options ?? [];
}

export function urgencyMatricesFor(definition) {
    return definition?.urgency_matrices ?? [];
}

export function urgencyContextsFor(definition) {
    return definition?.urgency_contexts ?? [];
}

export function transporterTypesFor(definition, matrixCode) {
    return urgencyMatricesFor(definition)
        .find((matrix) => optionCode(matrix) === matrixCode)
        ?.transporter_types ?? [];
}

export function urgencyOptionsFor(definition, matrixCode, transporterTypeCode) {
    if (categoryCode(definition) === 'CV') {
        return urgencyContextsFor(definition)
            .find((context) => optionCode(context) === matrixCode)
            ?.options ?? [];
    }

    const matrix = urgencyMatricesFor(definition).find((item) => optionCode(item) === matrixCode);
    if (!matrix) return [];
    if (matrix.code === 'structural_function') return matrix.options ?? [];

    return transporterTypesFor(definition, matrixCode)
        .find((type) => optionCode(type) === transporterTypeCode)
        ?.options ?? [];
}

export function calculateTechnicalGut(definition, values) {
    const category = categoryCode(definition);
    let gravity = null;
    let urgency = null;
    let trend = null;

    if (category === 'TAC') {
        gravity = definition?.sources?.gravity?.valid === false
            ? null
            : scoreOf(definition?.sources?.gravity?.score);
        urgency = scoreOf(findOption(
            definition?.sources?.urgency?.mapping,
            values.atmospheric_classification,
        )?.score);
        trend = scoreOf(findOption(definition?.trend_options, values.trend_option_code)?.score);
    } else if (category === 'CV' || category === 'REC') {
        const safety = scoreOf(findOption(definition?.safety_impact_options, values.safety_impact_code)?.score);
        const asset = scoreOf(findOption(definition?.asset_impact_options, values.asset_impact_code)?.score);

        gravity = safety !== null && asset !== null ? Math.max(safety, asset) : null;
        urgency = category === 'REC'
            ? scoreOf(findOption(
                urgencyOptionsFor(definition, values.urgency_matrix_code, values.transporter_type_code),
                values.urgency_option_code,
            )?.score)
            : scoreOf(findOption(
                urgencyOptionsFor(definition, values.urgency_context_code),
                values.urgency_option_code,
            )?.score);

        trend = scoreOf(findOption(
            trendOptionsFor(definition, values.trend_group_code),
            values.trend_option_code,
        )?.score);
    }

    if (gravity === null || urgency === null || trend === null) return null;

    return { gravity, urgency, trend, score: gravity * urgency * trend };
}

export function buildTechnicalGutPayload(definition, values) {
    const category = categoryCode(definition);
    const payload = {};

    if (values.condition) payload.condition = values.condition;

    if (category === 'TAC') {
        payload.atmospheric_classification = values.atmospheric_classification;
        payload.trend_option_code = values.trend_option_code;

        return payload;
    }

    payload.safety_impact_code = values.safety_impact_code;
    payload.asset_impact_code = values.asset_impact_code;

    if (category === 'REC') {
        payload.urgency_matrix_code = values.urgency_matrix_code;
        if (values.urgency_matrix_code === 'patio_port_transporter') {
            payload.transporter_type_code = values.transporter_type_code;
        }
    }
    if (category === 'CV') payload.urgency_context_code = values.urgency_context_code;
    payload.urgency_option_code = values.urgency_option_code;

    payload.trend_group_code = values.trend_group_code;
    payload.trend_option_code = values.trend_option_code;

    return payload;
}

export function technicalGutReady(definition, values) {
    const result = calculateTechnicalGut(definition, values);
    if (!result) return false;

    const category = categoryCode(definition);
    if (category === 'TAC') return Boolean(values.atmospheric_classification && values.trend_option_code);
    if (!values.safety_impact_code || !values.asset_impact_code) return false;

    if (category === 'CV') {
        return Boolean(values.urgency_context_code && values.urgency_option_code
            && values.trend_group_code && values.trend_option_code);
    }

    return category === 'REC'
        && values.urgency_matrix_code === 'patio_port_transporter'
        ? Boolean(values.transporter_type_code && values.trend_group_code && values.trend_option_code)
        : Boolean(values.urgency_option_code && values.trend_group_code && values.trend_option_code);
}

export function technicalOptionLabel(option, criterion = null) {
    if (!option) return '';

    const label = option.label ?? option.description ?? option.name ?? optionCode(option) ?? '';
    const score = scoreOf(option.score);

    return score === null || !criterion ? label : `${criterion} = ${score} — ${label}`;
}
