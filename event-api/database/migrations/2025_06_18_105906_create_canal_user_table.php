<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ModelStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('canal_user')) {
            return;
        }

        Schema::create('canal_user', function (Blueprint $table) {
            $table->unsignedInteger('canal_id');
            $table->unsignedInteger('user_id');

            $table->foreign('canal_id')
                ->references('id')
                ->on('canals')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))
                ->default(ModelStatus::Published->value);
            $table->timestamps();
            $table->primary(['canal_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canal_user');
    }
};
