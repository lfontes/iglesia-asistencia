<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('to_phone')->nullable();
            $table->string('recipient_wa_id')->nullable();
            $table->text('body')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('webhook_payload')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
