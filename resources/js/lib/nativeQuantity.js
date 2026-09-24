const STEEL_DENSITY = 7850;

function positiveNumber(value) {
    if (value === null || value === undefined || value === '') return null;

    const normalized = typeof value === 'string' ? value.trim().replace(',', '.') : value;
    const number = Number(normalized);

    return Number.isFinite(number) && number > 0 ? number : null;
}

function requiredValues(inputs, keys) {
    const values = Object.fromEntries(keys.map((key) => [key, positiveNumber(inputs[key])]));

    return Object.values(values).some((value) => value === null) ? null : values;
}

function steelWeight(areaInSquareMillimeters, lengthInMeters) {
    return STEEL_DENSITY * (areaInSquareMillimeters / 1_000_000) * lengthInMeters;
}

const recCalculators = {
    profile_w(inputs) {
        const value = requiredValues(inputs, ['flange_width', 'flange_thickness', 'web_height', 'web_thickness', 'length']);
        if (!value || value.web_height <= 2 * value.web_thickness) return null;
        const area = (value.flange_width * value.flange_thickness * 2)
            + ((value.web_height - 2 * value.web_thickness) * value.web_thickness);
        return steelWeight(area, value.length);
    },
    profile_l(inputs) {
        const value = requiredValues(inputs, ['width', 'thickness', 'length']);
        if (!value || value.width <= value.thickness) return null;
        return steelWeight((value.width * value.thickness) + ((value.width - value.thickness) * value.thickness), value.length);
    },
    profile_u(inputs) {
        const value = requiredValues(inputs, ['height', 'web_thickness', 'width', 'flange_thickness', 'length']);
        if (!value || value.width <= value.web_thickness) return null;
        const area = (value.height * value.web_thickness)
            + ((value.width - value.web_thickness) * value.flange_thickness * 2);
        return steelWeight(area, value.length);
    },
    profile_ue(inputs) {
        const value = requiredValues(inputs, ['height', 'web_thickness', 'flange_width', 'fold_width', 'flange_thickness', 'length']);
        if (!value || value.flange_width <= value.web_thickness || value.fold_width <= value.flange_thickness) return null;
        const area = (value.height * value.web_thickness)
            + (2 * (value.flange_width - value.web_thickness) * value.flange_thickness)
            + (2 * (value.fold_width - value.flange_thickness) * value.flange_thickness);
        return steelWeight(area, value.length);
    },
    smooth_plate: plateWeight,
    flat_bar: plateWeight,
    checkered_plate: plateWeight,
    guardrail(inputs) {
        const value = requiredValues(inputs, ['length']);
        return value ? value.length * 30 : null;
    },
    caged_ladder(inputs) {
        const value = requiredValues(inputs, ['length']);
        return value ? value.length * 60 : null;
    },
    tubular_profile(inputs) {
        const value = requiredValues(inputs, ['outer_diameter', 'thickness', 'length']);
        if (!value) return null;
        const innerDiameter = value.outer_diameter - 2 * value.thickness;
        if (innerDiameter <= 0) return null;
        const area = Math.PI * ((value.outer_diameter ** 2) - (innerDiameter ** 2)) / 4;
        return (area / 1_000_000) * (value.length / 1000) * STEEL_DENSITY;
    },
    unequal_angle(inputs) {
        const value = requiredValues(inputs, ['leg_1', 'leg_2', 'thickness', 'length']);
        if (!value || value.leg_2 <= value.thickness) return null;
        const area = (value.leg_1 * value.thickness) + ((value.leg_2 - value.thickness) * value.thickness);
        return steelWeight(area, value.length);
    },
    metalon(inputs) {
        const value = requiredValues(inputs, ['side_1', 'side_2', 'thickness', 'length']);
        if (!value || value.side_1 <= 2 * value.thickness) return null;
        const area = ((value.side_1 - 2 * value.thickness) * value.thickness * 2)
            + (value.side_2 * value.thickness * 2);
        return steelWeight(area, value.length);
    },
    tee_profile(inputs) {
        const value = requiredValues(inputs, ['flange_width', 'web_height', 'thickness', 'length']);
        if (!value || value.web_height <= value.thickness) return null;
        const area = (value.flange_width * value.thickness)
            + ((value.web_height - value.thickness) * value.thickness);
        return steelWeight(area, value.length);
    },
};

function plateWeight(inputs) {
    const value = requiredValues(inputs, ['width', 'length', 'thickness']);
    return value ? STEEL_DENSITY * value.width * value.length * value.thickness / 1_000_000_000 : null;
}

const manualRecElements = new Set(['bolted_connection', 'roof_sheet', 'floor_grating']);

export function calculateCivilQuantity(inputs) {
    const value = requiredValues(inputs, ['length', 'height', 'width', 'quantity']);
    if (!value) return null;

    const unitValue = value.length * value.height * value.width;

    return { unitValue, totalValue: unitValue * value.quantity, unit: 'm3', mode: 'calculated' };
}

export function calculateTacQuantity(inputs) {
    const area = positiveNumber(inputs.area);

    return area === null ? null : { unitValue: null, totalValue: area, unit: 'm2', mode: 'manual' };
}

export function calculateRecQuantity(inputs) {
    const element = inputs.element ?? inputs.rec_element ?? inputs.element_code;

    if (manualRecElements.has(element)) {
        const totalValue = positiveNumber(inputs.total_weight);
        return totalValue === null ? null : { unitValue: null, totalValue, unit: 'kg', mode: 'manual' };
    }

    const unitValue = recCalculators[element]?.(inputs) ?? null;
    const quantity = positiveNumber(inputs.quantity);
    if (unitValue === null || !Number.isFinite(unitValue) || unitValue <= 0 || quantity === null) return null;

    return { unitValue, totalValue: unitValue * quantity, unit: 'kg', mode: 'calculated' };
}

export function calculateNativeQuantity(category, inputs) {
    if (category === 'CV') return calculateCivilQuantity(inputs);
    if (category === 'TAC') return calculateTacQuantity(inputs);
    if (category === 'REC') return calculateRecQuantity(inputs);

    return null;
}

export function buildNativeQuantityPayload(category, values, definition = null) {
    if (category === 'CV') {
        return {
            ...pick(values, ['length', 'height', 'width']),
            // CIVIL items are recorded separately. Keep the persisted field for
            // compatibility and legacy items, but default every new item to one.
            quantity: values.quantity ?? 1,
        };
    }

    if (category === 'TAC') return pick(values, ['area']);

    if (category === 'REC') {
        const elementCode = values.element ?? values.rec_element ?? values.element_code;
        const element = definition?.elements?.find((item) => item.code === elementCode);
        const payload = { element: elementCode };

        if (element?.mode === 'manual' || manualRecElements.has(elementCode)) {
            payload.total_weight = values.total_weight;
            return payload;
        }

        for (const field of element?.fields ?? []) {
            if (field.key !== 'quantity') payload[field.key] = values[field.key];
        }
        // Calculated REC elements are recorded individually. Keep an existing
        // multiplier when editing legacy data, otherwise persist one.
        payload.quantity = values.quantity ?? 1;

        return payload;
    }

    return null;
}

function pick(values, fields) {
    return Object.fromEntries(fields.map((field) => [field, values[field]]));
}

export function formatNativeMeasurement(value, digits = 2) {
    const number = Number(value);
    if (!Number.isFinite(number)) return '—';

    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(number);
}

export function nativeUnitSymbol(unit) {
    return { m2: 'm²', m3: 'm³', kg: 'kg' }[unit] ?? unit ?? '';
}

export const nativeQuantityConstants = Object.freeze({ steelDensity: STEEL_DENSITY });
