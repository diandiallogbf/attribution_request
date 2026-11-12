<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels administrables par la DSI (sans développeur).
 * Les valeurs qui pilotent la logique métier restent des enums PHP stockés en string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('name');
            $table->string('phone_type')->index();
            $table->unsignedSmallInteger('storage_gb')->nullable();
            $table->unsignedBigInteger('unit_price_gnf')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand', 'name', 'storage_gb']);
        });

        // Grille de dotation : une ligne par catégorie de collaborateur.
        Schema::create('allowance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_category')->unique();
            $table->json('allowed_phone_types');
            $table->unsignedBigInteger('max_monthly_recharge_gnf');
            $table->unsignedTinyInteger('renewal_months');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Montants de recharge mensuelle proposés sur la fiche (10 000 → 4 000 000 GNF).
        Schema::create('recharge_amounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount_gnf')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recharge_amounts');
        Schema::dropIfExists('allowance_rules');
        Schema::dropIfExists('device_models');
        Schema::dropIfExists('operators');
    }
};
