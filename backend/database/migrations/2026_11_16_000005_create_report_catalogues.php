<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Municípios da província de Luanda segundo a Lei n.º 14/24 de 5 de Setembro.
        $catalogues = [
            'municipalities' => ['Belas', 'Cacuaco', 'Camama', 'Cazenga', 'Hoji ya Henda',
                'Ingombota', 'Kilamba', 'Kilamba Kiaxi', 'Maianga', 'Mulenvos', 'Mussulo',
                'Rangel', 'Samba', 'Sambizanga', 'Talatona', 'Viana'],
            'categories' => ['Vandalismo', 'Corrupção', 'Violência', 'Criminalidade', 'Outra'],
        ];
        foreach ($catalogues as $tableName => $names) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('active')->default(true);
            });
            DB::table($tableName)->insert(array_map(fn ($name) => ['name' => $name], $names));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('municipalities');
    }
};
