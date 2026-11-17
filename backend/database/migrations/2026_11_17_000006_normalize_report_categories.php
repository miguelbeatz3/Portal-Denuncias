<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const NAMES = [
        'Vandalismo' => 'Vandalismo e danos a bens',
        'Corrupção' => 'Corrupção e abuso de poder',
        'Violência' => 'Violência e ameaças',
        'Criminalidade' => 'Crimes e segurança pública',
        'Outra' => 'Outro assunto',
    ];

    private const ADDITIONS = [
        'Lixo e limpeza pública', 'Água e abastecimento', 'Esgotos e drenagem',
        'Vias e passeios', 'Iluminação pública', 'Trânsito e segurança rodoviária',
        'Obras e ocupação do espaço público', 'Poluição e ambiente',
        'Comércio e segurança alimentar', 'Trabalho e condições laborais',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $this->rename(self::NAMES);
            foreach (self::ADDITIONS as $name) {
                DB::table('categories')->insertOrIgnore(['name' => $name]);
            }
        });
    }

    public function down(): void
    {
        // A categoria pode já existir antes da migração ou ter denúncias associadas.
        // Manter as categorias adicionais no catálogo ao reverter os nomes.
        DB::transaction(fn () => $this->rename(array_flip(self::NAMES)));
    }

    private function rename(array $names): void
    {
        foreach ($names as $old => $new) {
            DB::table('categories')->where('name', $old)->update(['name' => $new]);
            DB::table('reports')->where('category', $old)->update(['category' => $new]);
        }
    }
};
