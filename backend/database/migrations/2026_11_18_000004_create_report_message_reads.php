<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_message_reads', function (Blueprint $table) {
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id');
            $table->primary(['report_id', 'operator_id']);
        });
        Schema::table('report_messages', fn (Blueprint $table) => $table->index(['report_id', 'sender', 'id'], 'report_messages_sender_index'));
    }

    public function down(): void
    {
        Schema::table('report_messages', fn (Blueprint $table) => $table->dropIndex('report_messages_sender_index'));
        Schema::dropIfExists('report_message_reads');
    }
};
