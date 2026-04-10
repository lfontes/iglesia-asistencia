<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('reply_to_provider_message_id')->nullable()->after('provider_message_id');
            $table->string('from_phone')->nullable()->after('to_phone');
            $table->string('conversation_key')->nullable()->after('recipient_wa_id');
            $table->string('message_type')->nullable()->after('direction');
            $table->timestamp('read_in_app_at')->nullable()->after('read_at');

            $table->index('conversation_key');
            $table->index(['conversation_key', 'created_at'], 'whatsapp_messages_conversation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_key']);
            $table->dropIndex('whatsapp_messages_conversation_created_idx');
            $table->dropColumn([
                'reply_to_provider_message_id',
                'from_phone',
                'conversation_key',
                'message_type',
                'read_in_app_at',
            ]);
        });
    }
};
