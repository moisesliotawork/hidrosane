<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Agregar el nuevo valor 'Pepper' al ENUM existente
        DB::statement("
            ALTER TABLE ventas 
            MODIFY COLUMN financiera ENUM('CREDIBOX','findirect','MONTJUIT','Pepper') 
            NULL DEFAULT NULL
        ");
    }

    public function down(): void
    {
        // Revertir el cambio, quitando 'Pepper'
        DB::statement("
            ALTER TABLE ventas 
            MODIFY COLUMN financiera ENUM('CREDIBOX','findirect','MONTJUIT') 
            NULL DEFAULT NULL
        ");
    }
};
