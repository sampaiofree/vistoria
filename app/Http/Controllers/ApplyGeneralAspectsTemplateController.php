<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\ApplyGeneralAspectsTemplate;
use App\Models\GeneralAspectsTemplate;
use App\Models\Inspection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApplyGeneralAspectsTemplateController extends Controller
{
    public function __invoke(Request $request, Inspection $inspection, GeneralAspectsTemplate $template, ApplyGeneralAspectsTemplate $action): JsonResponse
    {
        return response()->json($action->handle($inspection, $template, $request->user()));
    }
}
