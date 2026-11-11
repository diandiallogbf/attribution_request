<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 5)->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname')->nullable(); // « Surnom » sur la fiche
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // null pour les comptes 100 % SSO (phase Microsoft)
            $table->string('job_title')->nullable(); // « Fonction »
            $table->string('category')->nullable(); // EmployeeCategory
            // Contraintes ajoutées dans la migration organisation (tables créées après users).
            $table->foreignId('site_id')->nullable()->index();
            $table->foreignId('department_id')->nullable()->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete(); // N+1
            $table->string('azure_oid')->nullable()->unique(); // Microsoft Entra ID (SSO)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
