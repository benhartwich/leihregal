<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Wishes ────────────────────────────────────────────────────────────
        Schema::create('wishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->text('topic_freetext')->nullable();
            $table->enum('status', ['eingereicht', 'angenommen', 'abgelehnt', 'beobachten'])
                  ->default('eingereicht');
            $table->unsignedBigInteger('cluster_id')->nullable();
            $table->text('curator_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('cluster_id');
        });

        // ── Whitelist entries ─────────────────────────────────────────────────
        Schema::create('whitelist_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['verlag', 'autor']);
            $table->string('name');
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->constrained('users');
            $table->timestamps();

            $table->unique(['type', 'name']);
        });

        // ── Acquisition suggestions ───────────────────────────────────────────
        Schema::create('acquisition_suggestions', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['ki', 'wunsch'])->default('ki');
            $table->string('title');
            $table->string('isbn', 20)->nullable();
            $table->string('publisher')->nullable();
            $table->string('author')->nullable();
            $table->decimal('price_estimate', 8, 2)->nullable();
            $table->text('reason');
            $table->json('shop_urls')->nullable();
            $table->enum('status', ['offen', 'bestellt', 'verworfen', 'eingetroffen'])->default('offen');
            $table->foreignId('wish_id')->nullable()->constrained('wishes')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acquisition_suggestions');
        Schema::dropIfExists('whitelist_entries');
        Schema::dropIfExists('wishes');
    }
};
