<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_messages', fn (Blueprint $table) => $table->string('sender_entity_name')->nullable());
        Schema::table('reports', fn (Blueprint $table) => $table->text('final_verdict')->nullable());

        DB::table('report_messages')->where('sender', 'entity')->chunkById(500, function ($messages) {
            $routes = DB::table('report_events as event')->join('users as actor', 'actor.id', '=', 'event.user_id')
                ->where('actor.role', 'admin')->whereIn('event.report_id', $messages->pluck('report_id')->unique())
                ->where(fn ($query) => $query->where('event.message', 'like', 'Encaminhada para %')
                    ->orWhereNotNull('event.reassignment_reason'))
                ->orderByDesc('event.created_at')->orderByDesc('event.id')->get(['event.*'])->groupBy('report_id');

            foreach ($messages as $message) {
                $route = $routes->get($message->report_id, collect())->firstWhere('created_at', '<=', $message->created_at);
                // An older message and a transfer in the same second have no reliable order across tables.
                if (!$route || ($route->reassignment_reason !== null && $route->created_at === $message->created_at)) {
                    continue;
                }
                if (preg_match('/^(?:Encaminhada|Reencaminhada) para (.+)\.$/us', $route->message, $match)) {
                    DB::table('report_messages')->where('id', $message->id)->update(['sender_entity_name' => $match[1]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $table) => $table->dropColumn('final_verdict'));
        Schema::table('report_messages', fn (Blueprint $table) => $table->dropColumn('sender_entity_name'));
    }
};
