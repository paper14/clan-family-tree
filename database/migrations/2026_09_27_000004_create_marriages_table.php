<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/data-model.md §3. No clan_id: a marriage may span two clans.
 * Marriage order is `date` ascending with NULLs last, then `id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marriages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('husband_id')->nullable()->constrained('people');
            $table->foreignId('wife_id')->nullable()->constrained('people');
            $table->date('date')->nullable();
            $table->string('date_text')->nullable();
            $table->string('place')->nullable();
            $table->string('status', 12)->default('married');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marriages');
    }
};
