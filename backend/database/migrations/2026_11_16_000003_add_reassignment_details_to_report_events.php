<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_events', function (Blueprint $table) {
            $table->string('previous_entity_name')->nullable();
            $table->text('reassignment_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_events', fn (Blueprint $table) =>
            $table->dropColumn(['previous_entity_name', 'reassignment_reason']));
    }
};
