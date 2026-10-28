import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CreditCard, Lock, Pencil } from 'lucide-react';
import type { FormEvent } from 'react';

import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, Eyebrow, PageTitle } from '@/components/ui/card';
import { TextField } from '@/components/ui/text-field';
import { routes } from '@/lib/routes';

type Quote = {
    lines: Array<{ id: number; name: string; quantity: number; unitPrice: string; subtotal: string }>;
    players: number;
    subtotal: string;
    handlingFee: string | null;
    tax: string | null;
    total: string;
};

type Billing = Record<
    | 'billing_name_first'
    | 'billing_name_last'
    | 'billing_address_street'
    | 'billing_address_locality'
    | 'billing_address_town'
    | 'billing_address_region'
    | 'billing_address_country'
    | 'billing_address_postcode'
    | 'card_holder_name',
    string
>;

const BILLING_FIELDS: Array<{ name: keyof Billing; label: string; autoComplete: string; wide?: boolean; optional?: boolean }> = [
    { name: 'billing_name_first', label: 'First name', autoComplete: 'billing given-name' },
    { name: 'billing_name_last', label: 'Last name', autoComplete: 'billing family-name' },
    { name: 'billing_address_street', label: 'Address line 1', autoComplete: 'billing address-line1', wide: true },
    { name: 'billing_address_locality', label: 'Address line 2 (optional)', autoComplete: 'billing address-line2', wide: true, optional: true },
    { name: 'billing_address_town', label: 'City', autoComplete: 'billing address-level2' },
    { name: 'billing_address_region', label: 'State / County', autoComplete: 'billing address-level1' },
    { name: 'billing_address_postcode', label: 'Postcode', autoComplete: 'billing postal-code' },
    { name: 'billing_address_country', label: 'Country', autoComplete: 'billing country-name' },
];

/** "4111111111111111" -> "4111 1111 1111 1111" */
const formatCardNumber = (value: string) => value.replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim();

/** "1230" -> "12/30" */
const formatExpiry = (value: string) => {
    const digits = value.replace(/\D/g, '').slice(0, 4);

    return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
};

export default function Checkout({ quote, organiser, billing }: { quote: Quote; organiser: string; billing: Billing }) {
    const { flash } = usePage().props;
    const form = useForm<Billing & { card_number: string; expiry_date: string; security_code: string; payment?: string }>({ ...billing, card_number: '', expiry_date: '', security_code: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(routes.extraPlayersCheckout, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Payment" />
            <form onSubmit={submit} noValidate className="animate-rise space-y-4">
                <PageTitle eyebrow="Almost done" title="Payment" />

                <Card>
                    <div className="flex items-center justify-between">
                        <Eyebrow>Your order</Eyebrow>
                        <Link href={routes.extraPlayers} className="flex items-center gap-1 text-sm font-semibold text-brand">
                            <Pencil className="size-3.5" aria-hidden /> Change
                        </Link>
                    </div>
                    <ul className="mt-3 divide-y divide-white/[0.06]">
                        {quote.lines.map((line) => (
                            <li key={line.id} className="flex items-baseline justify-between gap-4 py-2.5">
                                <span className="min-w-0">
                                    <span className="block font-semibold">{line.name}</span>
                                    <span className="text-sm text-ink-400">
                                        {line.quantity} × {line.unitPrice}
                                    </span>
                                </span>
                                <span className="tabular">{line.subtotal}</span>
                            </li>
                        ))}
                    </ul>
                    <dl className="mt-2 space-y-1.5 border-t border-white/[0.06] pt-3 text-sm">
                        <Row label="Subtotal" value={quote.subtotal} />
                        {quote.handlingFee && <Row label="Handling fee" value={quote.handlingFee} />}
                        {quote.tax && <Row label="Tax" value={quote.tax} />}
                        <div className="flex items-baseline justify-between pt-2">
                            <dt className="text-base font-semibold">Total</dt>
                            <dd className="font-display text-3xl font-bold tabular text-brand">{quote.total}</dd>
                        </div>
                    </dl>
                </Card>

                <Card className="space-y-4">
                    <div>
                        <Eyebrow>Billing details</Eyebrow>
                        <p className="mt-1 text-sm text-ink-400">Booking organiser: {organiser}</p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {BILLING_FIELDS.map((field) => (
                            <TextField
                                key={field.name}
                                name={field.name}
                                label={field.label}
                                autoComplete={field.autoComplete}
                                className={field.wide ? 'sm:col-span-2' : undefined}
                                value={form.data[field.name]}
                                onChange={(e) => form.setData(field.name, e.target.value)}
                                error={form.errors[field.name]}
                            />
                        ))}
                    </div>
                </Card>

                <Card className="space-y-4">
                    <div className="flex items-center justify-between">
                        <Eyebrow>Card details</Eyebrow>
                        <span className="flex items-center gap-1.5 text-xs text-ink-400">
                            <Lock className="size-3.5" aria-hidden /> Secure payment by Worldpay
                        </span>
                    </div>
                    <TextField
                        name="card_number"
                        label="Card number"
                        inputMode="numeric"
                        autoComplete="cc-number"
                        placeholder="1234 5678 9012 3456"
                        value={form.data.card_number}
                        onChange={(e) => form.setData('card_number', formatCardNumber(e.target.value))}
                        error={form.errors.card_number}
                    />
                    <TextField
                        name="card_holder_name"
                        label="Name on card"
                        autoComplete="cc-name"
                        value={form.data.card_holder_name}
                        onChange={(e) => form.setData('card_holder_name', e.target.value)}
                        error={form.errors.card_holder_name}
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <TextField
                            name="expiry_date"
                            label="Expiry (MM/YY)"
                            inputMode="numeric"
                            autoComplete="cc-exp"
                            placeholder="MM/YY"
                            value={form.data.expiry_date}
                            onChange={(e) => form.setData('expiry_date', formatExpiry(e.target.value))}
                            error={form.errors.expiry_date}
                        />
                        <TextField
                            name="security_code"
                            label="Security code"
                            inputMode="numeric"
                            autoComplete="cc-csc"
                            placeholder="CVC"
                            maxLength={4}
                            value={form.data.security_code}
                            onChange={(e) => form.setData('security_code', e.target.value.replace(/\D/g, ''))}
                            error={form.errors.security_code}
                        />
                    </div>
                </Card>

                {(form.errors.payment || flash.error) && <Alert tone="error">{form.errors.payment ?? flash.error}</Alert>}

                <Button type="submit" size="lg" block disabled={form.processing}>
                    <CreditCard /> {form.processing ? 'Processing payment…' : `Pay ${quote.total}`}
                </Button>
            </form>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between text-ink-300">
            <dt>{label}</dt>
            <dd className="tabular">{value}</dd>
        </div>
    );
}
