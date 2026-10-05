<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInternalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isClient()) {
            if ($request->routeIs('dashboard')) {
                return redirect()->route('inspections.index');
            }

            abort_unless($request->isMethod('GET') && $request->routeIs(
                'inspections.index', 'inspections.show', 'inspections.report-preview',
                'inspections.report-defects.history',
                'inspections.quantitative', 'inspections.quantitative.export',
                'assessment-photos.show',
                'inspection-overview-photos.show', 'defect-location-map-versions.background',
                'branding.company', 'branding.client',
            ), 403);
        }

        return $next($request);
    }
}
