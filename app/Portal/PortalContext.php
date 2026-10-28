<?php

namespace App\Portal;

use App\Exceptions\Portal\InvalidUserKey;
use App\Exceptions\Portal\PortalAccessDenied;
use Closure;
use Illuminate\Http\Request;
use WeakMap;

/**
 * The current visitor's portal state: their access key (kept in the
 * session), the booking it unlocks and whether they are on a paintball
 * site's network. The booking is loaded at most once per request.
 */
class PortalContext
{
    private const SESSION_KEY = 'regos.user_key';

    /**
     * @var WeakMap<Request, Booking>
     */
    private WeakMap $bookings;

    /**
     * @param  Closure(): Request  $request  resolves the current request
     * @param  array<string, mixed>  $config  the "regos" configuration
     */
    public function __construct(
        private readonly Closure $request,
        private readonly PortalRepository $repository,
        private readonly NetworkLocator $locator,
        private readonly array $config,
    ) {
        $this->bookings = new WeakMap;
    }

    public function key(): ?UserKey
    {
        $value = $this->request()->session()->get(self::SESSION_KEY);

        try {
            return is_string($value) ? UserKey::fromString($value) : null;
        } catch (InvalidUserKey) {
            return null;
        }
    }

    public function hasKey(): bool
    {
        return $this->key() !== null;
    }

    public function signIn(UserKey $key): void
    {
        $request = $this->request();

        if ($request->session()->get(self::SESSION_KEY) !== $key->toString()) {
            $request->session()->migrate(true);
        }

        $request->session()->put(self::SESSION_KEY, $key->toString());
        unset($this->bookings[$request]);
    }

    public function signOut(): void
    {
        $request = $this->request();

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->migrate(true);
        unset($this->bookings[$request]);
    }

    /**
     * @throws PortalAccessDenied
     */
    public function booking(): Booking
    {
        $request = $this->request();

        if (isset($this->bookings[$request])) {
            return $this->bookings[$request];
        }

        $key = $this->key() ?? throw new PortalAccessDenied('Please use the link in the confirmation SMS or Email to access this portal');

        $this->repository->authorize($key);

        return $this->bookings[$request] = $this->repository->booking($key);
    }

    /**
     * The booking if this request has already loaded it, without calling HAPI.
     */
    public function loadedBooking(): ?Booking
    {
        return $this->bookings[$this->request()] ?? null;
    }

    /**
     * The visitor's own registration, when their key carries one that still
     * exists on the booking.
     */
    public function registration(): ?Registration
    {
        return $this->booking()->registration($this->key()?->registrationId);
    }

    public function links(): BookingLinks
    {
        return new BookingLinks($this->booking(), $this->config);
    }

    public function siteId(): ?int
    {
        return $this->locator->siteIdFor($this->request()->ip());
    }

    /**
     * On a paintball site's network (shared on-site devices).
     */
    public function isInternal(): bool
    {
        return $this->siteId() !== null;
    }

    private function request(): Request
    {
        return ($this->request)();
    }
}
