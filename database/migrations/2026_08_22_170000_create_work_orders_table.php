<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('work_order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('changes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_activities');
        Schema::dropIfExists('work_orders');
    }
};
