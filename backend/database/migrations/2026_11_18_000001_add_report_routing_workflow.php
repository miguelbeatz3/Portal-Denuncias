<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_reassignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('suggested_entity_id')->nullable()->constrained('entities');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->string('resolution')->nullable();
            $table->text('response')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['report_id', 'resolution']);
        });

        // Preserve treatment evidenced by an operator update after the latest assignment.
        $routingEvent = DB::table('report_events as event')->join('users as actor', 'actor.id', '=', 'event.user_id')
            ->whereColumn('event.report_id', 'reports.id')->where('actor.role', 'admin')
            ->where(fn ($query) => $query->where('event.message', 'like', 'Encaminhada para %')
                ->orWhereNotNull('event.reassignment_reason'))->orderByDesc('event.id')->limit(1);
        DB::table('reports')->select('reports.id')
            ->selectSub((clone $routingEvent)->select('event.id'), 'routing_event_id')
            ->selectSub((clone $routingEvent)->select('event.reassignment_reason'), 'reassignment_reason')
            ->where('status', 'Em tratamento')->whereNotNull('entity_id')
            ->chunkById(500, function ($reports) {
                $treated = DB::table('report_events as event')->join('users as actor', 'actor.id', '=', 'event.user_id')
                    ->where('actor.role', 'entity')->whereIn('event.report_id', $reports->pluck('id'))
                    ->groupBy('event.report_id')->selectRaw('max(event.id) as last_update, event.report_id')
                    ->pluck('last_update', 'report_id');
                foreach ($reports as $report) {
                    if ($report->routing_event_id && ($treated[$report->id] ?? 0) < $report->routing_event_id) {
                        DB::table('reports')->where('id', $report->id)->where('status', 'Em tratamento')
                            ->update(['status' => $report->reassignment_reason === null ? 'Encaminhada' : 'Reencaminhada']);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('reports')->whereIn('status', ['Encaminhada', 'Reencaminhada'])->update(['status' => 'Em tratamento']);
        Schema::dropIfExists('report_reassignment_requests');
    }
};
