<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_request_id');
            $table->string('agent_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->string('destination'); // DOMESTIC, INTERNATIONAL
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code'); // BASIC, PLUS, PREMIUM
            $table->decimal('sum_insured', 12, 2);
            $table->unsignedInteger('travel_days');
            $table->decimal('premium_amount', 10, 2);
            $table->json('pricing_breakdown');
            $table->string('status')->default('RECEIVED');
            $table->string('policy_number')->nullable()->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('last_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Enforce idempotency at database level for intake
            $table->unique(['agent_id', 'client_request_id'], 'uniq_agent_client_req');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};
