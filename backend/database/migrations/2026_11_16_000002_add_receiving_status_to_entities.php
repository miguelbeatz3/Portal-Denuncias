<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', fn (Blueprint $table) => $table->boolean('accepts_reports')->default(true));
    }

    public function down(): void
    {
        Schema::table('entities', fn (Blueprint $table) => $table->dropColumn('accepts_reports'));
    }
};
