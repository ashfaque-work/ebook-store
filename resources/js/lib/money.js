/**
 * Currency formatting for the store.
 *
 * Money crosses the wire as an integer number of paise, the same
 * representation the database and every Indian payment gateway use. Formatting
 * is the only place it becomes a decimal, and it happens here.
 */

const formatter = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Format paise for display: formatPaise(129950) -> "₹1,299.50".
 *
 * @param {number|string|null|undefined} paise
 * @returns {string}
 */
export function formatPaise(paise) {
    const value = Number.parseInt(paise ?? 0, 10);

    return formatter.format((Number.isFinite(value) ? value : 0) / 100);
}

/** Free books get a word rather than a zero amount. */
export function formatPrice(paise) {
    const value = Number.parseInt(paise ?? 0, 10);

    return value === 0 ? 'Free' : formatPaise(value);
}

/** Rupees, for prefilling an admin form from a stored paise amount. */
export function paiseToRupees(paise) {
    return (Number.parseInt(paise ?? 0, 10) / 100).toFixed(2);
}
