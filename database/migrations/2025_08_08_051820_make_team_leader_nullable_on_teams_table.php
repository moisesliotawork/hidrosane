<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeTeamLeaderNullableOnTeamsTable extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('team_leader_id')
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('team_leader_id')
                  ->nullable(false)
                  ->change();
        });
    }
}

