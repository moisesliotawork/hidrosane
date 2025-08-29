<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Establece show_phone = false (0) para TODAS las notas
        DB::table('notes')->update(['show_phone' => 0]);
    }
};
