<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/data-model.md §2. Only clan_id and given_name are required.
 * No unique constraint on any name, and no MySQL ENUM columns (strings validated in the app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // Identity and names
            $table->foreignId('clan_id')->constrained('clans'); // membership, not visibility
            $table->string('given_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('maiden_last_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('nickname')->nullable();
            $table->string('sex', 10)->default('unknown');

            // Life facts
            $table->boolean('is_living')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_date_text')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('death_date')->nullable();
            $table->string('death_date_text')->nullable();
            $table->string('death_place')->nullable();
            $table->string('burial_place')->nullable();
            $table->string('occupation')->nullable();
            $table->string('residence')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();

            // Descent
            $table->foreignId('father_id')->nullable()->constrained('people');
            $table->foreignId('mother_id')->nullable()->constrained('people');
            $table->string('clan_parent', 6)->nullable();
            $table->string('father_relation', 12)->default('biological');
            $table->string('mother_relation', 12)->default('biological');
            $table->text('parentage_note')->nullable();
            $table->boolean('hide_second_parent')->default(false);
            $table->unsignedSmallInteger('sibling_order'); // NOT NULL, no default: assigned on create
            $table->unsignedSmallInteger('generation')->nullable();

            // Subclan
            $table->boolean('is_subclan_head')->default(false);
            $table->string('subclan_name')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['father_id', 'mother_id', 'sibling_order']); // the sibling-set query
            $table->index(['clan_id', 'generation']);                   // people list sort, generation filter
            $table->index(['clan_id', 'is_subclan_head']);              // start dropdown, subclans list
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
