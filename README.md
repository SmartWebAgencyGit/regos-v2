# Player Portal (regos v2)

The Delta Force Paintball player portal: players open the link from their booking SMS / email, register and accept the declarations, get a boarding pass with a check-in QR code, and (optionally) pay to add players to their booking. On-site devices can pick a group from today's bookings.

It replaces the Mithril + Gulp front end and the `forms/public/regos` PHP API with a single Laravel 13 application. Pages are React (TypeScript) components served through [Inertia.js](https://inertiajs.com), so the portal behaves like a single-page app: no full page reloads, a persistent header and tab bar, and validation errors shown in place. Laravel still owns routing, validation, sessions and all HAPI / Worldpay calls. The portal has **no database of its own**: all data lives in HAPI (the Xena booking API).

## Local development

Requirements: PHP 8.3+, Composer, Node 20.19+.

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
npm run build            # or `npm run dev` while editing views
php artisan serve
php artisan regos:demo-links
```

`.env.example` sets `HAPI_FAKE=true` and `REGOS_PAYMENT_DRIVER=fake`. The portal then serves two demo bookings (Australia and UK/Liverpool) without touching the real HAPI. `regos:demo-links` prints sign-in links for them, and `--reset` clears demo registrations. The fake gateway approves every card except `4000 0000 0000 0002`. Both fakes refuse to run outside `local` / `testing`.

Tests (they fake HAPI and Worldpay over HTTP and never call either) and the TypeScript check:

```sh
php artisan test --compact
npm run typecheck
```

## Configuration

Business rules that used to be hard-coded across the JS bundle and PHP are in **`config/regos.php`**:

- per-country links (safety video, information pack, itinerary, parent info, privacy policy), handling-fee and tax rates, Worldpay merchant codes, indemnity wording variant
- per-site overrides (AU information packs, UK map PDFs) and restricted sites (Leicester)
- the **consent declarations**: which ones apply by country, site and age, in display order. The same list renders the form and validates it. Wording lives in `lang/en/consents.php`; the indemnity / T&C text is in `lang/en/rules.php`
- add-players package limits, booking-office interaction tag and email template
- on-site network ranges → HAPI site id

Production environment variables:

| Variable | Notes |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` | |
| `HAPI_FAKE=false`, `HAPI_URL`, `HAPI_USERNAME`, `HAPI_PASSWORD` | the portal's HAPI account |
| `REGOS_PAYMENT_DRIVER=worldpay`, `WORLDPAY_PASSWORD` | `WORLDPAY_URL` defaults to live; use `https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp` for testing |
| `SESSION_SECURE_COOKIE=true` | site is served over HTTPS |
| `TRUSTED_PROXIES` | only if a load balancer sits in front of PHP; otherwise leave blank |

## Deployment

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize
```

Point the web root at `public/` and use the standard Laravel nginx config (`try_files $uri $uri/ /index.php?$query_string;` with PHP handled only by `location ~ ^/index\.php(/|$)`). That routes the old `/forms/public/regos/index.php?action=qr|qr_captain|short` URLs, still referenced by emails, into the app.

The website form import (`/forms/public/forms/`) and SMS reply webhook (`/forms/public/sms/`) were deployed under the same host but belong to the separate "imports" repo and are **not** part of this app. Keep an nginx `location` serving them from the legacy code until they are migrated.

Payment attempts are logged (no card data) to `storage/logs/payments-*.log`.

## Code map

| Path | What |
| --- | --- |
| `app/Hapi` | HAPI client (`HttpHapi`) and the local demo fake (`FakeHapi`) |
| `app/Portal` | access key, booking/registration views, HAPI repository, consent rules, per-booking links, registration saving |
| `app/Portal/AddPlayers` | add-players pricing (server-side) and checkout |
| `app/Payments` | Worldpay XML Direct gateway and fake gateway |
| `app/Http/Middleware` | `portal` (valid key, optional `:registration`), `portal.internal` (on-site network), and `HandleInertiaRequests` (props shared with every page) |
| `app/Portal/BookingPresenter.php` | shapes bookings / registrations into page props (dates formatted in the site's timezone) |
| `resources/js/pages` | one React page per screen, named as in `Inertia::render()` |
| `resources/js/layouts/app-layout.tsx` | persistent shell: header, flash messages, bottom tab bar |
| `resources/js/components` | UI kit (`ui/`), countdown, safety video, tab bar |
| `resources/css/app.css` | theme tokens (colours, fonts) |

## Behaviour changes from the legacy portal

Intentional differences a reviewer should know about:

- **Prices are calculated on the server** from HAPI package data. The old API trusted the browser-supplied totals, so a modified request could pay any amount. The checkout shows the exact amount that will be charged.
- A payment counts as successful only when Worldpay returns `AUTHORISED` (or later). The old code treated any reply without an error as paid.
- Card holder name is sent to Worldpay from the card holder field (the old code sent the organiser's first name). Billing fields are XML-escaped, and amounts are integer cents (the old float maths could produce values like `1598.9999`).
- Order codes keep the `NAME_BOOKING_REGO…` shape, but the suffix is a full timestamp. The old minutes+seconds suffix repeated and could be rejected as a duplicate.
- Card number, expiry and CVC are never written to the session or logs. Payments are throttled and locked per booking against double submission.
- The on-site check uses the real client IP. The old code trusted any `X-Forwarded-For` header, which let anyone off-site list today's groups and their access keys.
- Every declaration shown on the form is now required server-side (except the marketing opt-in). Previously `gamedate_may_change`, `agree_dispute_resolution`, and `limited_car_parking` for UK bookings were shown as required but not enforced.
- Players who have registered but not accepted the declarations go to the verify page instead of a fresh registration form, which created duplicates. The verify page works again: the old one referenced an undefined variable.
- "Click here" (check your details) edits the existing registration instead of creating a new one.
- A temporary HAPI outage shows a "try again" page and keeps the visitor signed in.
- Address line 2 is optional on the payment form. The information pack link is shown whenever one exists, not only for bookings with a group name. The Cobham map PDF is now linked.
- Not carried over, because nothing in production linked to them: the `/upgradepackage`, `/extraitems`, `/addextraitem`, `/agerules` and `/dayitinerary` pages, and the JSON API actions they used.
