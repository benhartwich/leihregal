<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media MODIFY COLUMN age_recommendation TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media MODIFY COLUMN age_recommendation VARCHAR(50) NULL');
    }
};
