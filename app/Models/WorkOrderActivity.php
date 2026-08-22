<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderActivity extends Model
{
    protected $fillable = [
        'work_order_id',
        'user_id',
        'type',
        'changes',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
