<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->date('reserved_from')->nullable()->after('notified_at');
            $table->date('reserved_until')->nullable()->after('reserved_from');
            $table->text('notes')->nullable()->after('reserved_until');
        });

        // Make position nullable so dated reservations don't need a queue slot
        DB::statement('ALTER TABLE reservations MODIFY COLUMN position SMALLINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['reserved_from', 'reserved_until', 'notes']);
        });
        DB::statement('ALTER TABLE reservations MODIFY COLUMN position SMALLINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
