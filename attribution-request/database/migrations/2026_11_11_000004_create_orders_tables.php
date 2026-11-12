<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique(); // n° de BC ou référence opérateur
            $table->string('type');
            $table->string('status')->index();
            $table->string('supplier')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('total_gnf')->nullable();
            $table->text('notes')->nullable();
            // Comptabilité (saisie par la Finance) : facture fournisseur, imputation, règlement.
            $table->string('invoice_reference')->nullable();
            $table->date('invoiced_at')->nullable();
            $table->unsignedBigInteger('invoice_amount_gnf')->nullable();
            $table->string('cost_center', 30)->nullable();
            $table->date('paid_at')->nullable()->index();
            $table->text('finance_notes')->nullable();
            $table->foreignId('accounted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accounted_at')->nullable();
            $table->timestamps();

            $table->index('ordered_at');
        });

        // Un BC peut regrouper plusieurs demandes, ou servir à réapprovisionner le stock (sans demande).
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('received_quantity')->default(0);
            $table->unsignedBigInteger('unit_price_gnf')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
