<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->string('description')->nullable());

        $descriptions = [
            'Vandalismo e danos a bens' => 'Destruição ou dano intencional de um bem, como partir bancos de jardim, vidros ou equipamento público.',
            'Corrupção e abuso de poder' => 'Pedido de dinheiro ou favores para prestar um serviço, ou uso indevido de um cargo para obter vantagens.',
            'Violência e ameaças' => 'Agressões físicas, intimidação ou ameaças contra uma pessoa, em casa, na rua ou noutro local.',
            'Crimes e segurança pública' => 'Furtos, roubos, burlas ou outras ocorrências que ponham em risco a segurança das pessoas ou dos seus bens.',
            'Lixo e limpeza pública' => 'Lixo acumulado na rua, contentores a transbordar ou falhas na recolha de resíduos.',
            'Água e abastecimento' => 'Fugas de água em condutas, roturas na rede ou falhas no abastecimento de água.',
            'Esgotos e drenagem' => 'Esgoto a céu aberto, valas entupidas ou ruas inundadas porque a água da chuva não escoa.',
            'Vias e passeios' => 'Buracos na estrada, passeios danificados ou tampas de sarjeta em falta.',
            'Iluminação pública' => 'Candeeiros apagados, postes danificados ou ruas sem iluminação à noite.',
            'Trânsito e segurança rodoviária' => 'Semáforos avariados, corridas ilegais ou estacionamento que impede a passagem.',
            'Obras e ocupação do espaço público' => 'Obras, vedações ou materiais de construção que ocupam a via ou impedem o acesso a um espaço público.',
            'Poluição e ambiente' => 'Queima de resíduos, descarga de óleo numa vala ou outras situações que contaminem o ar, a água ou o solo.',
            'Comércio e segurança alimentar' => 'Venda de produtos fora do prazo, alimentos deteriorados ou falta de higiene num estabelecimento comercial.',
            'Trabalho e condições laborais' => 'Falta de equipamento de protecção, condições de trabalho perigosas ou salários em atraso.',
            'Outro assunto' => 'Escolha esta opção se o assunto da sua denúncia não estiver entre as categorias apresentadas.',
        ];
        foreach ($descriptions as $name => $description) {
            DB::table('categories')->where('name', $name)->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
