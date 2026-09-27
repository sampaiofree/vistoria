<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Inspection;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Serialize HTTP edits and transitions before FormRequest authorization reads the state. */
final class LockInspectionMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->user()?->organization_id === null) {
            return $next($request);
        }

        $parameters = $request->route()?->parameters() ?? [];
        $inspectionId = null;
        foreach ($parameters as $model) {
            if ($model instanceof Inspection) {
                $inspectionId = $model->id;
                break;
            }
            if ($model instanceof Model && $model->getAttribute('inspection_id') !== null) {
                $inspectionId = $model->getAttribute('inspection_id');
                break;
            }
        }
        if ($inspectionId === null) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $parameters, $inspectionId): Response {
            $inspection = Inspection::query()->forOrganization($request->user()->organization_id)
                ->lockForUpdate()->find($inspectionId);
            if ($inspection === null) {
                return $next($request);
            }
            foreach ($parameters as $name => $model) {
                if ($model instanceof Inspection) {
                    $request->route()->setParameter($name, $inspection);
                } elseif ($model instanceof Model && (int) $model->getAttribute('inspection_id') === (int) $inspectionId) {
                    $model->refresh();
                    $model->setRelation('inspection', $inspection);
                }
            }

            return $next($request);
        });
    }
}
