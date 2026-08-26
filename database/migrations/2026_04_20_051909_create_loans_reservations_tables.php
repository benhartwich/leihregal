<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Loans ─────────────────────────────────────────────────────────────
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('borrowed_at')->useCurrent();
            $table->timestamp('due_at');
            $table->timestamp('returned_at')->nullable();
            $table->tinyInteger('rating')->unsigned()->nullable();   // 0=negativ, 1=positiv
            $table->text('rating_comment')->nullable();
            $table->timestamps();

            $table->index(['media_id', 'returned_at']);
            $table->index(['user_id', 'returned_at']);
        });

        // ── Reservations ──────────────────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');                // FIFO queue position
            $table->enum('status', ['wartend', 'bereit', 'abgeholt', 'storniert'])->default('wartend');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['media_id', 'status', 'position']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('loans');
    }
};
