<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_bulk_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('use_case');
            $table->date('fecha_referencia');
            $table->string('period_hash', 64);
            $table->text('period_summary')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['use_case', 'period_hash'], 'whatsapp_bulk_dispatches_use_case_period_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bulk_dispatches');
    }
};
