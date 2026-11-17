<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_events', fn (Blueprint $table) => $table->text('false_report_reason')->nullable());
    }

    public function down(): void
    {
        Schema::table('report_events', fn (Blueprint $table) => $table->dropColumn('false_report_reason'));
    }
};
