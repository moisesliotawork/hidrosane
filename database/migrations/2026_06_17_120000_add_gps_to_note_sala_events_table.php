<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('note_sala_events', 'lat')) {
            return;
        }

        Schema::table('note_sala_events', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('sent_at');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('note_sala_events', 'lat')) {
            return;
        }

        Schema::table('note_sala_events', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
