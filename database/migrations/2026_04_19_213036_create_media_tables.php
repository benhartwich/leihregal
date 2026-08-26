<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Main media table ──────────────────────────────────────────────────
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Core metadata
            $table->enum('type', [
                'buch', 'gefuehlskarten', 'spiel', 'zeitschrift',
                'arbeitsmaterial', 'digital',
            ])->default('buch');

            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->smallInteger('year')->unsigned()->nullable();
            $table->string('isbn', 20)->nullable()->unique();
            $table->char('language', 5)->default('de');

            // Availability
            $table->enum('status', [
                'verfuegbar', 'ausgeliehen', 'reserviert',
                'in_aufbereitung', 'verloren', 'ausgemustert',
            ])->default('verfuegbar');

            // Internal barcode – generated on create, unique
            $table->string('internal_code', 20)->unique();

            // AI-generated fields
            $table->text('summary')->nullable();
            $table->text('target_group')->nullable();
            $table->string('age_recommendation', 50)->nullable();
            $table->text('practical_use')->nullable();

            // Physical
            $table->string('cover_path')->nullable();
            $table->string('location', 100)->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // FULLTEXT index for search (title, author, summary)
        DB::statement('ALTER TABLE media ADD FULLTEXT ft_media_search (title, author, summary)');

        // ── Tags (many-to-many, simple join table) ────────────────────────────
        Schema::create('media_tags', function (Blueprint $table) {
            $table->unsignedBigInteger('media_id');
            $table->string('tag', 80);
            $table->primary(['media_id', 'tag']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });

        // ── Embeddings (VECTOR for semantic search) ───────────────────────────
        // MariaDB 12.3 supports native VECTOR type with cosine distance index.
        // Dimension 1536 = OpenAI text-embedding-3-small output size.
        DB::statement('
            CREATE TABLE media_embeddings (
                media_id BIGINT UNSIGNED NOT NULL,
                embedding VECTOR(1536) NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (media_id),
                FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
                VECTOR INDEX vec_embedding (embedding) DISTANCE=cosine
            ) ENGINE=InnoDB
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('media_embeddings');
        Schema::dropIfExists('media_tags');
        Schema::dropIfExists('media');
    }
};
