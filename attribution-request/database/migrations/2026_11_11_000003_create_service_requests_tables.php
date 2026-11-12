<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moteur générique de demandes : sert la fiche Téléphonie et, plus tard, la fiche Profil utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference', 20)->nullable()->unique(); // attribuée à la première soumission
            $table->string('type')->index();
            $table->string('status')->index();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            // Null pour une création de profil : le nouveau collaborateur n'a pas encore de compte
            // (son identité est dans profile_request_details puis figée dans beneficiary_snapshot).
            $table->foreignId('beneficiary_id')->nullable()->constrained('users')->restrictOnDelete();
            // Photo figée du bénéficiaire (service, fonction, catégorie…) au moment de la soumission :
            // la fiche reste exacte même si le collaborateur change de poste ensuite.
            $table->json('beneficiary_snapshot')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('policy_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // « Date d'exécution / Exécuté par (Nom & Prénom IT) » des deux fiches.
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('execution_notes')->nullable();
            // Délégation DSI : la commande / l'exécution est confiée à un membre de l'équipe IT.
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable();
            $table->text('delegation_note')->nullable(); // consignes internes, non montrées au collaborateur
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['beneficiary_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index(['handler_id', 'status']);
        });

        // Compteur mensuel (« 2611 »), commun aux deux fiches, verrouillé en transaction : pas de doublon de N° de fiche.
        Schema::create('request_sequences', function (Blueprint $table) {
            $table->id();
            $table->char('period', 4)->unique();
            $table->unsignedInteger('last_number')->default(0);
        });

        // Une ligne par validation attendue. Plusieurs lignes pour une même étape = validations parallèles.
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_role')->nullable();
            $table->foreignId('direction_id')->nullable()->constrained()->nullOnDelete(); // validation métier
            $table->string('decision')->default('pending')->index();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['assigned_to_id', 'decision']);
            $table->index(['assigned_role', 'decision']);
        });

        // Journal immuable : chronologie affichée + piste d'audit.
        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('comment')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('request_approvals');
        Schema::dropIfExists('request_sequences');
        Schema::dropIfExists('service_requests');
    }
};
