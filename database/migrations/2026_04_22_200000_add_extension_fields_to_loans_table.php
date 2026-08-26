<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->tinyInteger('extension_count')->unsigned()->default(0)->after('returned_at');
            $table->timestamp('last_reminded_at')->nullable()->after('extension_count');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['extension_count', 'last_reminded_at']);
        });
    }
};
