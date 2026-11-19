<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->date('next_action_on')->nullable();
            $table->index(['entity_id', 'next_action_on']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['entity_id', 'next_action_on']);
            $table->dropColumn('next_action_on');
        });
    }
};
