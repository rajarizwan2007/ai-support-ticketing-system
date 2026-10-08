<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the subdomain (acme.<app host> → slug "acme")
 * and makes it current. There is no default tenant: an unknown or missing
 * subdomain is a 404. See docs/adr/0001-multi-tenancy.md.
 */
class ResolveOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = Organization::where('slug', $this->subdomain($request))->first();

        abort_if($organization === null, 404);

        $organization->makeCurrent();

        abort_if(
            $request->user() !== null && $request->user()->organization_id !== $organization->id,
            403,
        );

        return $next($request);
    }

    /**
     * The single label in front of the app host, or null when there isn't exactly one.
     */
    private function subdomain(Request $request): ?string
    {
        $suffix = '.'.parse_url(config('app.url'), PHP_URL_HOST);
        $host = $request->getHost();

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        return str_contains($subdomain, '.') ? null : $subdomain;
    }
}
