<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->string('service_scope', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
            $table->dropColumn(['service_scope', 'address', 'phone', 'email']);
        });
    }
};
