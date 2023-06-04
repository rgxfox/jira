<?php

namespace Rgxfox\Jira\Http\Middleware;

use Illuminate\Http\Request;
use Rgxfox\Jira\Services\BaseService;
use Closure;

class GoogleSpreadsheetMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $hash = request('hash');
        if (!BaseService::checkHash($hash)) {
            return response()->json([
                'error' => 'Wrong hash'
            ])->setStatusCode(403);
        } else {
            return $next($request);
        }
    }
}
