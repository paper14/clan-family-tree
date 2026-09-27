<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/data-model.md §1. The founder keys point into `people`, which doesn't exist yet;
 * they get their foreign keys in a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('founder_id')->nullable();
            $table->unsignedBigInteger('founder_spouse_id')->nullable();
            $table->string('origin_place')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
