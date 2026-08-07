<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odpovede v inboxe. Odpoveď je bežná správa s otočeným odosielateľom
 * a príjemcom; `parent_message_id` z nej robí vlákno, aby sa v zozname dala
 * ukázať ako súčasť konverzácie a nie ako samostatný dopyt.
 *
 * Pri zmazaní pôvodnej správy sa odpovede nemažú — len sa odviažu a ostanú
 * v inboxe ako samostatné záznamy. Mazanie správ dnes aj tak nikde nie je.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_message_id')->nullable()->after('id');

            $table->foreign('parent_message_id')->references('id')->on('messages')->nullOnDelete();
            $table->index('parent_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['parent_message_id']);
            $table->dropIndex(['parent_message_id']);
            $table->dropColumn('parent_message_id');
        });
    }
};
