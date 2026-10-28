<?php

namespace App\Http\Middleware;

use App\Portal\PortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only allow requests from a paintball site's network.
 */
class EnsureInternalNetwork
{
    public function __construct(private readonly PortalContext $portal) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->portal->isInternal()) {
            return redirect()->route('portal.entry');
        }

        return $next($request);
    }
}
