const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

/**
 * Display only. Amounts travel as decimal strings; the float conversion here
 * is the last step before the screen and never feeds a calculation.
 */
export function formatMoney(amount: string | null | undefined): string {
    if (amount == null || amount === '') {
        return '—';
    }

    return usd.format(Number(amount));
}
