<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL 8+: Usamos ROW_NUMBER() para tomar la sesión más reciente por usuario (tie-break por id)
        DB::statement(<<<'SQL'
UPDATE users u
LEFT JOIN (
    SELECT user_id, end_time
    FROM (
        SELECT
            ws.user_id,
            ws.end_time,
            ROW_NUMBER() OVER (
                PARTITION BY ws.user_id
                ORDER BY ws.updated_at DESC, ws.id DESC
            ) AS rn
        FROM work_sessions ws
    ) ranked
    WHERE ranked.rn = 1
) last ON last.user_id = u.id
SET u.is_active = CASE
    WHEN last.user_id IS NULL THEN 0
    WHEN last.end_time IS NULL THEN 1
    ELSE 0
END
SQL);
    }

    public function down(): void
    {
        // Si necesitas revertir, dejamos a todos inactivos (o ajusta según tu preferencia)
        DB::statement('UPDATE users SET is_active = 0');
    }
};
