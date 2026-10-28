export function cn(...classes: Array<string | false | null | undefined>): string {
    return classes.filter(Boolean).join(' ');
}

export function money(cents: number, symbol: string): string {
    return symbol + (cents / 100).toFixed(2);
}

/**
 * Whole years between an ISO date of birth and "today" (both YYYY-MM-DD,
 * today in the site's timezone), or null while the date is incomplete.
 */
export function ageOn(dateOfBirth: string, today: string): number | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateOfBirth)) {
        return null;
    }

    const [by, bm, bd] = dateOfBirth.split('-').map(Number);
    const [ty, tm, td] = today.split('-').map(Number);
    let age = ty - by;

    if (tm < bm || (tm === bm && td < bd)) {
        age--;
    }

    return Math.max(age, 0);
}

export function telHref(phone: string): string {
    return 'tel:' + phone.replace(/\s+/g, '');
}
