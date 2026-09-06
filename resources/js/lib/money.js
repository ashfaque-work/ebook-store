/**
 * Currency formatting for the store.
 *
 * One place decides how money looks. Prices arrive from the API as decimal
 * strings ("299.00"), so parse once here rather than in every template.
 */

const formatter = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Format a rupee amount for display, e.g. formatInr('1299.5') → "₹1,299.50".
 *
 * @param {string|number|null|undefined} rupees
 * @returns {string}
 */
export function formatInr(rupees) {
    const value = Number.parseFloat(rupees ?? 0);

    return formatter.format(Number.isFinite(value) ? value : 0);
}

/** Free books get a word rather than "₹0.00". */
export function formatPrice(rupees) {
    const value = Number.parseFloat(rupees ?? 0);

    return value === 0 ? 'Free' : formatInr(value);
}
