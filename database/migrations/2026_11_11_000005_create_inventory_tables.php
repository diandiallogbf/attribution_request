<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parc : appareils (téléphones, tablettes… et plus tard PC) et lignes SIM, et leur historique d'attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_model_id')->constrained()->restrictOnDelete();
            $table->string('imei', 15)->nullable()->unique();
            $table->string('serial_number')->nullable()->unique();
            $table->string('status')->index();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->date('acquired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained()->restrictOnDelete();
            $table->string('msisdn', 16)->unique(); // E.164 : +224622123456
            $table->string('iccid', 20)->nullable()->unique();
            $table->string('plan_type');
            $table->json('options')->nullable();
            $table->unsignedBigInteger('monthly_recharge_gnf')->nullable();
            $table->boolean('is_fleet')->default(true);
            $table->string('status')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->morphs('assignable'); // Device | SimLine
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->json('setup_checklist')->nullable(); // compte Microsoft, Intune, puce insérée…
            $table->timestamp('received_at')->nullable();
            $table->string('receipt_signature_path')->nullable();
            $table->timestamp('policy_accepted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('sim_lines');
        Schema::dropIfExists('devices');
    }
};
