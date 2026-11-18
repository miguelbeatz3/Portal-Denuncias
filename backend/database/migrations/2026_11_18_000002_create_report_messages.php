<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 20);
            $table->text('message');
            $table->uuid('client_token')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'client_token']);
            $table->index(['report_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_messages');
    }
};
