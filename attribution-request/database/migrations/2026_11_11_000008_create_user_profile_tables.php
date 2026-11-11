<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche profil utilisateur (AGL-GIN-DSI-F-0008) : accès, applications métier, matériel.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Applications métier : la direction propriétaire valide les accès (règle imprimée sur la fiche).
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('owner_direction_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->boolean('requires_business_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('profile_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('action')->nullable();
            $table->string('profile_type')->nullable();
            $table->date('internship_ends_at')->nullable();
            $table->date('effective_at')->nullable(); // date de prise de poste / de départ

            // Nouveau collaborateur (création) : identité saisie sur la fiche.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('matricule', 5)->nullable();
            $table->string('job_title')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete(); // futur N+1

            // Accès utilisateur
            $table->string('reference_profile')->nullable(); // « même profil que… »
            $table->boolean('needs_email')->default(false);
            $table->text('distribution_lists')->nullable();
            $table->boolean('needs_as400')->default(false);
            $table->boolean('needs_internet')->default(false);
            $table->string('other_application')->nullable();

            // Matériels
            $table->json('equipment')->nullable();
            $table->string('gsm_number', 16)->nullable();

            $table->text('comment')->nullable();
            $table->string('execution_ticket')->nullable(); // n° de ticket / référence d'exécution
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('profile_request_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_request_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_request_detail_id', 'application_id'], 'profile_request_application_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_request_applications');
        Schema::dropIfExists('profile_request_details');
        Schema::dropIfExists('applications');
    }
};
