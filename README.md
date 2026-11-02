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
| `HAPI_TIMEOUT` (20), `HAPI_WRITE_TIMEOUT` (50) | seconds to wait for HAPI reads and saves. Creating a registration also sends its SMS, so saves can be slow. Keep the save limit below the web server's own (nginx: 60s) |
| `REGOS_PAYMENT_DRIVER=worldpay`, `WORLDPAY_PASSWORD` | `WORLDPAY_URL` defaults to live; use `https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp` for testing |
| `SESSION_SECURE_COOKIE=true` | site is served over HTTPS |
| `TRUSTED_PROXIES` | only if a load balancer sits in front of PHP; otherwise leave blank |

## Deployment

The legacy portal is deployed by a GitLab CI job (`ssh` + `git pull`) to `/var/www/virtual/regos` on the production server, and the same nginx host serves the separate "imports" endpoints from that code. Install v2 next to it so switching over, and back, is an nginx change.

### Server requirements

- PHP 8.3+ (PHP-FPM) with `ctype`, `curl`, `dom`, `fileinfo`, `iconv`, `mbstring`, `openssl`, `simplexml`, `tokenizer` and `xml`
- Composer 2
- Node 20.19+, only to build the front end (or build in CI and copy `public/build` to the server)
- Outbound access to HAPI (`HAPI_URL`) and to `secure.worldpay.com` on port 443

No database, queue worker or cron job is needed.

### Repository access

The server pulls from GitHub with a read-only deploy key. This is an SSH key that belongs to the repository, not to a person, and doesn't expire. As the deploy user (e.g. `dfadmin`):

```sh
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -N '' -C "regos-v2 deploy ($(hostname))" -f ~/.ssh/regos-v2-deploy
cat >> ~/.ssh/config <<'EOF'
Host github-regos-v2
    HostName github.com
    User git
    IdentityFile ~/.ssh/regos-v2-deploy
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
cat ~/.ssh/regos-v2-deploy.pub
```

Add the printed public key to the repository under Settings → Deploy keys → Add deploy key. Leave "Allow write access" unticked. Then run `ssh -T github-regos-v2`:

- The first time, check the fingerprint it shows against [GitHub's published ones](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/githubs-ssh-key-fingerprints).
- It should reply with the repository's name.
- If the network blocks outbound port 22, add `Port 443` and use `HostName ssh.github.com`.

The `github-regos-v2` alias makes git use this key even if the deploy user has other GitHub keys. GitHub allows each deploy key on only one repository.

### First install

```sh
cd /var/www/virtual
git clone github-regos-v2:<owner>/regos-v2.git regos-v2   # later pulls reuse the deploy key
cd regos-v2
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env      # then set the production variables above
php artisan key:generate  # once; changing APP_KEY later signs everyone out
php artisan optimize
```

Make `storage/` and `bootstrap/cache/` writable by the PHP-FPM user, e.g. `chgrp -R nginx storage bootstrap/cache && chmod -R g+rwX storage bootstrap/cache` (the group may be `www-data`). With SELinux enforcing, also run `chcon -R -t httpd_sys_rw_content_t storage bootstrap/cache`.

### nginx

Keep the existing `server_name`, `listen` and SSL lines, and change the rest of the site's `server` block to:

```nginx
root /var/www/virtual/regos-v2/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/path/to/php8.3-fpm.sock;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    fastcgi_hide_header X-Powered-By;
}

# Website form import and SMS reply webhook: separate "imports" code, still served from the legacy checkout.
location ~ ^/forms/public/(forms|sms)/ {
    root /var/www/virtual/regos/public;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/path/to/legacy-php-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

PHP runs only through `index.php`, which also routes the old `/forms/public/regos/index.php?action=qr|qr_captain|short` URLs (still in emails) into the app. Check with `nginx -t` before `systemctl reload nginx`.

### Check before switching over

On staging, or a temporary hostname pointing at v2:

- `/up` returns 200.
- A booking link (`/?k=…`) opens the dashboard. This proves HAPI is reachable and the credentials work.
- From a device on a site's network, `/groups` lists today's groups. If it redirects to the start page instead, PHP isn't seeing the site's `10.x` address: set `TRUSTED_PROXIES` to the proxy's IP.
- Registering a player sends a real SMS and email, so use a test booking.
- Payments: only against `WORLDPAY_URL=https://secure-test.worldpay.com/...` with test credentials. The fake gateway refuses to run outside `local`.

### Switch over and roll back

Switch by reloading nginx with the server block above. Roll back by restoring the previous block and reloading. Leave `/var/www/virtual/regos` in place: the imports endpoints run from it. Visitors are signed in by their booking link, so anyone mid-registration at the switch just re-opens it.

### Updating

```sh
cd /var/www/virtual/regos-v2
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize
```

Reload PHP-FPM afterwards if OPcache doesn't revalidate files. Configuration is cached, so run `php artisan optimize` again after any `.env` change.

### Logs

Errors go to `storage/logs/laravel.log` (set `LOG_STACK=daily` to rotate it). Payment attempts are logged, without card data, to `storage/logs/payments-*.log` and kept for 400 days.

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
- "Check my contact details" opens with the player's details filled in; the old form opened blank. Saving still creates a new registration, as before, because HAPI doesn't let the portal's account edit a customer. The old registration stays on the booking, unverified.
- A temporary HAPI outage shows a "try again" page and keeps the visitor signed in.
- Address line 2 is optional on the payment form. The information pack link is shown whenever one exists, not only for bookings with a group name. The Cobham map PDF is now linked.
- Not carried over, because nothing in production linked to them: the `/upgradepackage`, `/extraitems`, `/addextraitem`, `/agerules` and `/dayitinerary` pages, and the JSON API actions they used.
