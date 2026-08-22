<?php

namespace App\Http\Requests;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->organization_id;
    }

    public function rules(): array
    {
        $organizationId = $this->user()->organization_id;

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(WorkOrder::STATUSES)],
            'priority' => ['required', Rule::in(WorkOrder::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
