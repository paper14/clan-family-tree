<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/data-model.md §4. Cascades (person → photos, marriage → family pictures) are done
 * in the application, not by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans');
            $table->foreignId('person_id')->nullable()->constrained('people');
            $table->foreignId('marriage_id')->nullable()->constrained('marriages');
            $table->string('kind', 16);
            $table->string('file_path');
            $table->text('caption')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'kind']);
            $table->index(['marriage_id', 'kind']);
            $table->index(['clan_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
