<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('ip_address');
            $table->index('created_at', 'log_aktivitas_created_at_index');
            $table->index(['aksi', 'created_at'], 'log_aktivitas_aksi_created_index');
            $table->index(['target_type', 'target_id'], 'log_aktivitas_target_index');
        });

        $this->createAppendOnlyTriggers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropAppendOnlyTriggers();

        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->dropIndex('log_aktivitas_created_at_index');
            $table->dropIndex('log_aktivitas_aksi_created_index');
            $table->dropIndex('log_aktivitas_target_index');
            $table->dropColumn('metadata');
        });
    }

    private function createAppendOnlyTriggers(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER log_aktivitas_prevent_update BEFORE UPDATE ON log_aktivitas BEGIN SELECT RAISE(ABORT, 'Log aktivitas tidak dapat diubah'); END");
            DB::unprepared("CREATE TRIGGER log_aktivitas_prevent_delete BEFORE DELETE ON log_aktivitas BEGIN SELECT RAISE(ABORT, 'Log aktivitas tidak dapat dihapus'); END");

            return;
        }

        DB::unprepared("CREATE TRIGGER log_aktivitas_prevent_update BEFORE UPDATE ON log_aktivitas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Log aktivitas tidak dapat diubah'");
        DB::unprepared("CREATE TRIGGER log_aktivitas_prevent_delete BEFORE DELETE ON log_aktivitas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Log aktivitas tidak dapat dihapus'");
    }

    private function dropAppendOnlyTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS log_aktivitas_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS log_aktivitas_prevent_delete');
    }
};
