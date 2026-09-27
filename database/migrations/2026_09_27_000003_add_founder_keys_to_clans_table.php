<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->foreign('founder_id')->references('id')->on('people');
            $table->foreign('founder_spouse_id')->references('id')->on('people');
        });
    }

    public function down(): void
    {
        Schema::table('clans', function (Blueprint $table) {
            $table->dropForeign(['founder_id']);
            $table->dropForeign(['founder_spouse_id']);
        });
    }
};
