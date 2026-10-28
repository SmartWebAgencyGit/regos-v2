<?php

namespace App\Http\Middleware;

use App\Exceptions\Portal\PortalAccessDenied;
use App\Portal\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid access key with open booking access. Pass
 * "registration" (portal:registration) to also require that the key
 * identifies the visitor's own registration.
 */
class EnsurePortalAccess
{
    public function __construct(private readonly PortalContext $portal) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requirement = null): Response
    {
        if (! $this->portal->hasKey()) {
            return redirect()->route('portal.entry');
        }

        // A HAPI outage is rendered by the exception handler and keeps the visitor signed in.
        try {
            $this->portal->booking();
        } catch (PortalAccessDenied $exception) {
            $this->portal->signOut();

            return redirect()->route('portal.entry')->with('portal_error', $exception->getMessage());
        }

        if ($requirement === 'registration' && $this->portal->registration() === null) {
            return redirect()->route('portal.entry');
        }

        return $next($request);
    }
}
