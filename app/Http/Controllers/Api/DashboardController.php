<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $organizationId = $request->user()->organization_id;
        $today = now()->startOfDay();

        $baseQuery = WorkOrder::query()
            ->where('organization_id', $organizationId);

        $open = (clone $baseQuery)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $overdue = (clone $baseQuery)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $highPriority = (clone $baseQuery)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereIn('priority', ['high', 'urgent'])
            ->count();

        $completedLast7Days = (clone $baseQuery)
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        $recent = (clone $baseQuery)
            ->with(['client', 'assignee'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'metrics' => [
                'open' => $open,
                'overdue' => $overdue,
                'high_priority' => $highPriority,
                'completed_last_7_days' => $completedLast7Days,
            ],
            'recent_work_orders' => WorkOrderResource::collection($recent),
        ]);
    }
}
