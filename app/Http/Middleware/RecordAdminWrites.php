<?php

namespace App\Http\Middleware;

use App\Domains\Identity\AdminAuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing request to an /admin route.
 *
 * This is the catch-all, not the whole story. Domain code that knows what it
 * changed calls AdminAuditRecorder directly with before/after state — the
 * ledger adjustment form in Checkpoint 7 is the first example. This middleware
 * guarantees that even an action nobody remembered to instrument leaves a row
 * saying who touched what and when.
 *
 * Attached to the web group rather than to individual routes, so a future
 * admin route cannot be added without it. Maveren's H-1 was not a missing
 * logging function; the function existed. It was that every call site had to
 * remember to call it, and none did.
 */
class RecordAdminWrites
{
    /**
     * Verbs that can change something. GET and HEAD are reads, and recording
     * them would bury the writes in noise.
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly AdminAuditRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldRecord($request)) {
            return $response;
        }

        // Recorded after the response so the status can be captured, and
        // recorded whatever that status is: a refused admin write is a thing
        // you want to find later, not a thing to stay quiet about.
        $this->recorder->record(
            action: $this->action($request),
            after: [
                'status' => $response->getStatusCode(),
                'route' => $request->route()?->getName(),
            ],
        );

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        return $request->is('admin', 'admin/*')
            && in_array($request->getMethod(), self::WRITE_METHODS, true);
    }

    /**
     * A stable, readable name for what was attempted. The route name when there
     * is one, since that survives a path change; the verb and path otherwise.
     */
    private function action(Request $request): string
    {
        $name = $request->route()?->getName();

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return strtolower($request->getMethod()).' '.$request->path();
    }
}
