<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détails spécifiques à la fiche Téléphonie (relations 1–1 avec service_requests).
 * Une demande porte sur un téléphone, une ligne, ou les deux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->string('requested_phone_type')->nullable();
            $table->string('other_type_label')->nullable();
            $table->text('detailed_reason')->nullable();
            // Ancien téléphone : lié au parc si connu, sinon saisi librement (reprise de l'existant).
            $table->foreignId('previous_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('previous_device_label')->nullable();
            $table->string('previous_device_serial')->nullable();
            // Décision DSI
            $table->foreignId('approved_device_model_id')->nullable()->constrained('device_models')->nullOnDelete();
            $table->boolean('allowance_compliant')->nullable();
            $table->text('allowance_override_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sim_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('action')->nullable();
            $table->string('plan_type')->nullable();
            $table->json('options')->nullable();
            $table->foreignId('existing_sim_line_id')->nullable()->constrained('sim_lines')->nullOnDelete();
            $table->string('existing_msisdn', 16)->nullable();
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('requested_monthly_recharge_gnf')->nullable();
            $table->unsignedBigInteger('approved_monthly_recharge_gnf')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sim_request_details');
        Schema::dropIfExists('phone_request_details');
    }
};
