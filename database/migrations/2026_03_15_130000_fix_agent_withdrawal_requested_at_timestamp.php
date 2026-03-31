<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agent_withdrawals')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE agent_withdrawals MODIFY requested_at DATETIME NOT NULL');
        DB::statement('ALTER TABLE agent_withdrawals MODIFY processed_at DATETIME NULL');
        DB::statement('UPDATE agent_withdrawals SET requested_at = created_at WHERE created_at IS NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('agent_withdrawals')) {
            return;
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE agent_withdrawals MODIFY requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE agent_withdrawals MODIFY processed_at TIMESTAMP NULL DEFAULT NULL');
    }
};
