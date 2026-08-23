<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\WorkOrderIntakeParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderIntakeController extends Controller
{
    public function __invoke(Request $request, WorkOrderIntakeParser $parser): JsonResponse
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 403, 'User is not assigned to an organization.');

        $validated = $request->validate([
            'text' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        return response()->json([
            'data' => $parser->parse($validated['text'], $organization),
        ]);
    }
}
