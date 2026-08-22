<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\WorkOrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user()->load('organization');
    });

    Route::get('/team-members', function (Request $request) {
        abort_unless($request->user()->organization, 403, 'User is not assigned to an organization.');

        return $request->user()->organization->users()
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();
    });

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('work-orders', WorkOrderController::class);
});
