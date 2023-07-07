<?php

namespace MobileNowGroup\LaravelNotification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequestId
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $this->addRequestId($request);

        return $next($request);
    }

    /**
     * @return $this
     */
    private function addRequestId($request): self
    {
        $request->headers->add([
            config('dingding.request_id_key') => $this->generateRequestId(),
        ]);

        return $this;
    }

    private function generateRequestId(): string
    {
        return sprintf('%s%s', date('YmdHis'), uniqid());
    }
}
