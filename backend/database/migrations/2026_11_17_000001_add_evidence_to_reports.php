<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->text('evidence_description')->nullable();
            $table->json('evidence_files')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn(['evidence_description', 'evidence_files']));
    }
};
