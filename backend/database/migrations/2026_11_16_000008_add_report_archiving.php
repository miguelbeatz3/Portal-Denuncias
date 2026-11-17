<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_events', fn (Blueprint $table) => $table->text('archive_reason')->nullable());
        $this->convertFalseReports('Concluída', 'Arquivada', 'Denúncia arquivada.');
    }

    public function down(): void
    {
        $this->convertFalseReports('Arquivada', 'Concluída', 'Tratamento concluído.');
        Schema::table('report_events', fn (Blueprint $table) => $table->dropColumn('archive_reason'));
    }

    private function convertFalseReports(string $from, string $to, string $message): void
    {
        DB::transaction(function () use ($from, $to, $message) {
            $classified = DB::table('report_events')->whereNotNull('false_report_reason');
            DB::table('reports')->where('status', $from)
                ->whereIn('id', (clone $classified)->select('report_id'))->update(['status' => $to]);
            $classified->update(['message' => $message]);
        });
    }
};
