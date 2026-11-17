<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->string('category')->nullable()->change());
    }

    public function down(): void
    {
        DB::table('reports')->whereNull('category')->update(['category' => 'Por classificar']);
        Schema::table('reports', fn (Blueprint $table) => $table->string('category')->nullable(false)->change());
    }
};
