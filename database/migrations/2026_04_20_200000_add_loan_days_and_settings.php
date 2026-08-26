<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Defaults
        DB::table('settings')->insert([
            ['key' => 'loan_default_days', 'value' => '14', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'loan_reminder_days', 'value' => '2',  'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('media', function (Blueprint $table) {
            $table->unsignedSmallInteger('loan_days')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('loan_days');
        });
        Schema::dropIfExists('settings');
    }
};
