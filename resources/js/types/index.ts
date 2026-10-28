export type Flash = {
    success?: string;
    warning?: string;
    error?: string;
};

export type SharedProps = {
    brand: { name: string; phone: string; website: string };
    devMode: { demoBookings: boolean; simulatedPayments: boolean };
    onSite: boolean;
    portal: { party: string; hasRegistration: boolean; registrationComplete: boolean } | null;
    flash: Flash;
};

export type GameDate = {
    iso: string;
    day: string;
    date: string;
    short: string;
    time: string;
};

export type Booking = {
    id: number;
    party: string;
    organiser: string;
    group: string | null;
    site: string;
    venue: string;
    paxBooked: number;
    registeredCount: number;
    gameDate: GameDate;
};

export type Registration = {
    id: number;
    name: string;
    firstName: string;
    initials: string;
    confirmed: boolean;
    declarationsAccepted: boolean;
    complete: boolean;
};

export type ConsentLink = { text: string; href: string | null } | null;

export type Consent = {
    input: string;
    label: string;
    link: ConsentLink;
    minAge: number | null;
    maxAge: number | null;
    required: boolean;
};

export type SafetyVideo = { embed: string; url: string };

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}
