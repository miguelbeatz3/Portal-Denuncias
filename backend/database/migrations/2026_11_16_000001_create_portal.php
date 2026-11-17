<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->string('role');
            $t->foreignId('entity_id')->nullable()->constrained('entities');
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('secret_hash', 64);
            $t->string('name')->nullable();
            $t->string('category');
            $t->string('location');
            $t->text('description');
            $t->string('status')->default('Recebida')->index();
            $t->foreignId('entity_id')->nullable()->constrained('entities');
            $t->timestamps();
        });
        Schema::create('report_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->text('message');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['report_events', 'reports', 'users', 'entities'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
