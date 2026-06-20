<?php

use App\Enums\FileType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');

            // Polymorphic relation
            $table->morphs('fileable');

            // File metadata
            $table->string('name', 191)->nullable();
            $table->string('original_name', 191);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('mime_type', 191);
            $table->string('disk', 50)->default('public');
            $table->string('path', 2048);
            $table->string('thumb', 2048)->nullable();
            $table->string('large', 2048)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->enum('type', array_map(fn($status) => $status->value, FileType::cases()))
                ->default(FileType::FILE->value);

            // Extra info
            $table->boolean('is_primary')->default(false);
            $table->json('meta')->nullable();

            // Soft delete + timestamps
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'is_primary']);
            $table->index('mime_type');
            $table->index('checksum');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
