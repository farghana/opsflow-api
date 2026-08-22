<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WorkOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 403, 'User is not assigned to an organization.');

        $allowedSorts = ['order_number', 'title', 'status', 'priority', 'due_date', 'created_at', 'updated_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $search = mb_strtolower(trim($request->string('search')->toString()));

        $workOrders = $organization->workOrders()
            ->with(['client:id,name,company_name', 'assignee:id,name,email'])
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($query) use ($like) {
                    $query->whereRaw('LOWER(order_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereHas('client', function ($clientQuery) use ($like) {
                            $clientQuery->whereRaw('LOWER(name) LIKE ?', [$like])
                                ->orWhereRaw("LOWER(COALESCE(company_name, '')) LIKE ?", [$like]);
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')->toString()))
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
            ->when($request->filled('assignee_id'), fn ($query) => $query->where('assignee_id', $request->integer('assignee_id')))
            ->when($request->boolean('overdue'), function ($query) {
                $query->whereDate('due_date', '<', today())
                    ->whereNotIn('status', ['completed', 'cancelled']);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return WorkOrderResource::collection($workOrders);
    }

    public function store(WorkOrderRequest $request): WorkOrderResource
    {
        $data = $request->validated();
        $data['organization_id'] = $request->user()->organization_id;
        $data['order_number'] = $this->generateOrderNumber();
        $data['completed_at'] = $data['status'] === 'completed' ? now() : null;

        $workOrder = WorkOrder::create($data);
        $workOrder->activities()->create([
            'user_id' => $request->user()->id,
            'type' => 'created',
            'changes' => [
                'status' => ['from' => null, 'to' => $workOrder->status],
                'assignee_id' => ['from' => null, 'to' => $workOrder->assignee_id],
            ],
        ]);

        return new WorkOrderResource($workOrder->load(['client', 'assignee']));
    }

    public function show(Request $request, int $workOrder): WorkOrderResource
    {
        return new WorkOrderResource(
            $this->findWorkOrder($request, $workOrder)
                ->load(['client', 'assignee', 'activities.user'])
        );
    }

    public function update(WorkOrderRequest $request, int $workOrder): WorkOrderResource
    {
        $workOrderModel = $this->findWorkOrder($request, $workOrder);
        $before = $workOrderModel->only(['status', 'assignee_id', 'priority', 'due_date']);
        $data = $request->validated();

        if ($data['status'] === 'completed' && $workOrderModel->status !== 'completed') {
            $data['completed_at'] = now();
        } elseif ($data['status'] !== 'completed') {
            $data['completed_at'] = null;
        }

        $workOrderModel->update($data);

        $changes = [];
        foreach (['status', 'assignee_id', 'priority', 'due_date'] as $field) {
            $old = $before[$field] instanceof \DateTimeInterface ? $before[$field]->format('Y-m-d') : $before[$field];
            $newValue = $workOrderModel->{$field};
            $new = $newValue instanceof \DateTimeInterface ? $newValue->format('Y-m-d') : $newValue;
            if ((string) $old !== (string) $new) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        if ($changes !== []) {
            $type = Arr::has($changes, 'status') ? 'status_changed' : (Arr::has($changes, 'assignee_id') ? 'assignment_changed' : 'updated');
            $workOrderModel->activities()->create([
                'user_id' => $request->user()->id,
                'type' => $type,
                'changes' => $changes,
            ]);
        }

        return new WorkOrderResource($workOrderModel->refresh()->load(['client', 'assignee', 'activities.user']));
    }

    public function destroy(Request $request, int $workOrder): Response
    {
        $this->findWorkOrder($request, $workOrder)->delete();

        return response()->noContent();
    }

    private function findWorkOrder(Request $request, int $workOrder): WorkOrder
    {
        $organization = $request->user()->organization;
        abort_unless($organization, 403, 'User is not assigned to an organization.');

        return $organization->workOrders()->findOrFail($workOrder);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'WO-'.now()->format('ym').'-'.Str::upper(Str::random(6));
        } while (WorkOrder::where('order_number', $number)->exists());

        return $number;
    }
}
