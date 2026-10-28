import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, ShieldCheck } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import { VideoEmbed } from '@/components/video-embed';
import { Button } from '@/components/ui/button';
import { Card, PageTitle } from '@/components/ui/card';
import { ConsentToggle } from '@/components/ui/consent-toggle';
import { TextField } from '@/components/ui/text-field';
import { routes } from '@/lib/routes';
import { ageOn, cn } from '@/lib/utils';
import type { Booking, Consent, SafetyVideo } from '@/types';

type Props = {
    booking: Booking;
    editing: boolean;
    player: { name_first: string; name_last: string; date_of_birth: string; mobile: string; email: string; postcode: string };
    consents: Consent[];
    guardianConsent: string;
    guardianAge: number;
    safetyVideo: SafetyVideo;
    privacyPolicy: string | null;
    onSite: boolean;
    today: string;
    latestDateOfBirth: string;
};

type FormData = Record<string, string | boolean>;

const STEPS = ['Player', 'Safety brief', 'Declarations'];
const DETAIL_FIELDS = ['name_first', 'name_last', 'date_of_birth', 'guardian', 'guardian_permission', 'mobile', 'email', 'postcode'];

const REQUIRED_DETAILS: Record<string, string> = {
    name_first: 'Please enter your first name',
    name_last: 'Please enter your last name',
    date_of_birth: 'Please enter your date of birth',
    mobile: 'Please enter your mobile number',
    email: 'Please enter your email address',
    postcode: 'Please enter your postcode',
};

export default function Register({ booking, editing, player, consents, guardianConsent, guardianAge, safetyVideo, privacyPolicy, onSite, today, latestDateOfBirth }: Props) {
    const [step, setStep] = useState(0);

    const form = useForm<FormData>({
        ...player,
        guardian: '',
        guardian_permission: false,
        editing,
        ...Object.fromEntries(consents.map((consent) => [consent.input, consent.input === 'safety_brief' && onSite])),
    });

    const age = ageOn(String(form.data.date_of_birth), today);
    const needsGuardian = age !== null && age < guardianAge;
    const applies = (consent: Consent) =>
        age === null ? consent.maxAge === null : (consent.minAge === null || age >= consent.minAge) && (consent.maxAge === null || age <= consent.maxAge);

    const safetyBrief = consents.find((consent) => consent.input === 'safety_brief');
    const declarations = consents.filter((consent) => consent.input !== 'safety_brief' && applies(consent));
    const requiredDeclarations = declarations.filter((consent) => consent.required);
    const acceptedCount = requiredDeclarations.filter((consent) => form.data[consent.input] === true).length;

    const text = (field: string) => String(form.data[field] ?? '');
    const update = (field: string, value: string | boolean) => {
        form.setData(field, value);
        form.clearErrors(field);
    };

    const goTo = (target: number) => {
        setStep(target);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const stepOf = (field: string) => (DETAIL_FIELDS.includes(field) ? 0 : field === 'safety_brief' ? 1 : 2);

    const next = () => {
        const missing: Record<string, string> = {};

        if (step === 0) {
            for (const [field, message] of Object.entries(REQUIRED_DETAILS)) {
                if (text(field).trim() === '') {
                    missing[field] = message;
                }
            }
            if (needsGuardian && text('guardian').trim() === '') {
                missing.guardian = "Please enter the Guardian's name";
            }
            if (needsGuardian && form.data.guardian_permission !== true) {
                missing.guardian_permission = 'The Guardian must provide permission';
            }
        }

        if (step === 1 && safetyBrief && form.data.safety_brief !== true) {
            missing.safety_brief = 'Please watch the safety brief';
        }

        if (Object.keys(missing).length > 0) {
            form.setError(missing);

            return;
        }

        goTo(step + 1);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (step < STEPS.length - 1) {
            next();

            return;
        }

        form.transform((data) => {
            const payload: FormData = { editing: data.editing };

            for (const field of DETAIL_FIELDS) {
                if (needsGuardian || !field.startsWith('guardian')) {
                    payload[field] = data[field];
                }
            }
            for (const consent of [...(safetyBrief ? [safetyBrief] : []), ...declarations]) {
                payload[consent.input] = data[consent.input];
            }

            return payload;
        });

        form.post(routes.register, {
            preserveScroll: true,
            onError: (errors) => goTo(Math.min(...Object.keys(errors).map(stepOf))),
        });
    };

    return (
        <>
            <Head title={editing ? 'Check your details' : 'Register'} />
            <form onSubmit={submit} noValidate className="animate-rise space-y-5">
                <PageTitle eyebrow={booking.venue} title={editing ? 'Check your details' : 'Player registration'}>
                    {editing
                        ? 'Correct anything that’s wrong, then confirm the safety brief and declarations again. We’ll send a new verification link.'
                        : 'Three quick steps and you’re ready for battle.'}
                </PageTitle>

                <ol className="grid grid-cols-3 gap-2" aria-label="Registration steps">
                    {STEPS.map((label, index) => (
                        <li key={label}>
                            <button
                                type="button"
                                disabled={index > step}
                                onClick={() => goTo(index)}
                                aria-current={index === step ? 'step' : undefined}
                                className="w-full text-left disabled:cursor-default"
                            >
                                <span className={cn('block h-1.5 rounded-full transition-colors', index <= step ? 'bg-brand' : 'bg-ink-700')} />
                                <span className={cn('mt-2 flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider', index === step ? 'text-ink-100' : 'text-ink-400')}>
                                    {index < step && <Check className="size-3.5 text-brand" aria-hidden />}
                                    {label}
                                </span>
                            </button>
                        </li>
                    ))}
                </ol>

                {step === 0 && (
                    <Card className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField name="name_first" label="First name" autoComplete="given-name" value={text('name_first')} onChange={(e) => update('name_first', e.target.value)} error={form.errors.name_first} />
                            <TextField name="name_last" label="Last name" autoComplete="family-name" value={text('name_last')} onChange={(e) => update('name_last', e.target.value)} error={form.errors.name_last} />
                        </div>
                        <TextField
                            name="date_of_birth"
                            type="date"
                            label="Date of birth"
                            autoComplete="bday"
                            max={latestDateOfBirth}
                            value={text('date_of_birth')}
                            onChange={(e) => update('date_of_birth', e.target.value)}
                            error={form.errors.date_of_birth}
                            hint={`Players under ${guardianAge} need a parent or guardian’s permission.`}
                        />

                        {needsGuardian && (
                            <div className="animate-rise space-y-4 rounded-2xl border border-brand/25 bg-brand/[0.04] p-4">
                                <p className="flex items-center gap-2 text-sm font-semibold text-brand">
                                    <ShieldCheck className="size-4" aria-hidden /> Parent or guardian
                                </p>
                                <TextField name="guardian" label="Guardian’s full name" autoComplete="name" value={text('guardian')} onChange={(e) => update('guardian', e.target.value)} error={form.errors.guardian} />
                                <ConsentToggle
                                    name="guardian_permission"
                                    label={guardianConsent}
                                    checked={form.data.guardian_permission === true}
                                    onChange={(checked) => update('guardian_permission', checked)}
                                    error={form.errors.guardian_permission}
                                />
                            </div>
                        )}

                        <TextField
                            name="mobile"
                            type="tel"
                            label="Mobile"
                            autoComplete="tel"
                            value={text('mobile')}
                            onChange={(e) => update('mobile', e.target.value)}
                            error={form.errors.mobile}
                            hint="We’ll text your verification link to this number."
                        />
                        <TextField
                            name="email"
                            type="email"
                            label="Email"
                            autoComplete="email"
                            value={text('email')}
                            onChange={(e) => update('email', e.target.value)}
                            error={form.errors.email}
                            hint="We’ll email your registration here too."
                        />
                        <TextField name="postcode" label="Postcode" autoComplete="postal-code" value={text('postcode')} onChange={(e) => update('postcode', e.target.value)} error={form.errors.postcode} />

                        {privacyPolicy && (
                            <p className="text-sm text-ink-400">
                                We look after your details as set out in our{' '}
                                <a href={privacyPolicy} target="_blank" rel="noopener" className="font-semibold text-ink-100 underline underline-offset-4">
                                    Privacy Policy
                                </a>
                                .
                            </p>
                        )}
                    </Card>
                )}

                {step === 1 && (
                    <Card className="space-y-4">
                        <div>
                            <h2 className="font-display text-2xl font-bold uppercase">Watch the safety brief</h2>
                            <p className="mt-1 text-ink-300">It covers goggles, the game zones and how the day runs, and it’s required for every player.</p>
                        </div>
                        <VideoEmbed video={safetyVideo} />
                        {safetyBrief && (
                            <ConsentToggle
                                name="safety_brief"
                                label={safetyBrief.label}
                                link={safetyBrief.link}
                                checked={form.data.safety_brief === true}
                                onChange={(checked) => update('safety_brief', checked)}
                                error={form.errors.safety_brief}
                            />
                        )}
                    </Card>
                )}

                {step === 2 && (
                    <div className="space-y-3">
                        <div className="flex items-end justify-between px-1">
                            <div>
                                <h2 className="font-display text-2xl font-bold uppercase">Declarations</h2>
                                <p className="text-sm text-ink-300">Please read and switch on each one.</p>
                            </div>
                            <p className="font-display text-xl font-bold tabular text-brand" aria-live="polite">
                                {acceptedCount}/{requiredDeclarations.length}
                            </p>
                        </div>
                        {declarations.map((consent) => (
                            <ConsentToggle
                                key={consent.input}
                                name={consent.input}
                                label={consent.label}
                                link={consent.link}
                                optional={!consent.required}
                                checked={form.data[consent.input] === true}
                                onChange={(checked) => update(consent.input, checked)}
                                error={form.errors[consent.input]}
                            />
                        ))}
                    </div>
                )}

                <div className="flex gap-3 pt-1">
                    {step > 0 && (
                        <Button variant="secondary" size="lg" onClick={() => goTo(step - 1)} aria-label="Previous step">
                            <ArrowLeft />
                        </Button>
                    )}
                    <Button type="submit" size="lg" block disabled={form.processing}>
                        {step < STEPS.length - 1 ? (
                            <>
                                Continue <ArrowRight />
                            </>
                        ) : form.processing ? (
                            'Saving…'
                        ) : editing ? (
                            'Save my details'
                        ) : (
                            <>
                                <Check /> Register me
                            </>
                        )}
                    </Button>
                </div>
            </form>
        </>
    );
}
