<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kto z tímu kanála chce aké notifikácie (App\Enums\CanalNotificationTopic).
     *
     * Riadok existuje len vtedy, keď sa nastavenie líši od predvoľby roly —
     * zmena roly tak automaticky zmení aj predvolené notifikácie a nikomu
     * netreba nič dopĺňať.
     */
    public function up(): void
    {
        if (Schema::hasTable('canal_notification_settings')) {
            return;
        }

        Schema::create('canal_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('canal_id');
            $table->unsignedInteger('user_id');
            $table->string('topic', 32);
            $table->boolean('enabled');
            $table->timestamps();

            $table->foreign('canal_id')->references('id')->on('canals')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['canal_id', 'user_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canal_notification_settings');
    }
};
