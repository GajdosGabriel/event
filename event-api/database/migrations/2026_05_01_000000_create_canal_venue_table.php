<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\ModelStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canal_venue', function (Blueprint $table) {
            $table->unsignedInteger('canal_id');
            $table->unsignedInteger('venue_id');
            $table->boolean('is_owner')->default(false);
            $table->enum('status', array_map(fn($status) => $status->value, ModelStatus::cases()))
                ->default(ModelStatus::Published->value);
            $table->timestamps();

            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
            $table->foreign('venue_id')->references('id')->on('venues')->cascadeOnDelete();
            $table->primary(['canal_id', 'venue_id']);
        });

        if (Schema::hasColumn('venues', 'canal_id')) {
            DB::table('venues')
                ->whereNotNull('canal_id')
                ->orderBy('id')
                ->select(['id', 'canal_id', 'created_at', 'updated_at'])
                ->chunk(500, function ($venues): void {
                    foreach ($venues as $venue) {
                        DB::table('canal_venue')->updateOrInsert(
                            [
                                'canal_id' => $venue->canal_id,
                                'venue_id' => $venue->id,
                            ],
                            [
                                'is_owner' => true,
                                'status' => ModelStatus::Published->value,
                                'created_at' => $venue->created_at ?? now(),
                                'updated_at' => $venue->updated_at ?? now(),
                            ]
                        );
                    }
                });

            Schema::table('venues', function (Blueprint $table) {
                $table->dropForeign(['canal_id']);
                $table->dropIndex(['canal_id']);
                $table->dropColumn('canal_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (! Schema::hasColumn('venues', 'canal_id')) {
                $table->unsignedInteger('canal_id')->nullable()->after('id');
                $table->index('canal_id');
                $table->foreign('canal_id')->references('id')->on('canals');
            }
        });

        if (Schema::hasTable('canal_venue') && Schema::hasColumn('venues', 'canal_id')) {
            DB::table('canal_venue')
                ->orderBy('venue_id')
                ->select(['canal_id', 'venue_id'])
                ->chunk(500, function ($links): void {
                    foreach ($links as $link) {
                        DB::table('venues')
                            ->where('id', $link->venue_id)
                            ->whereNull('canal_id')
                            ->update(['canal_id' => $link->canal_id]);
                    }
                });
        }

        Schema::dropIfExists('canal_venue');
    }
};
