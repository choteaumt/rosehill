<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cemetery_id')->constrained()->cascadeOnDelete();

            // Name
            $table->string('last_name');
            $table->string('first_name')->nullable();

            // Age — integer when clean, raw string preserved when not
            $table->unsignedSmallInteger('age_at_death')->nullable();
            $table->string('age_raw')->nullable();

            // Interment date — parsed date + raw string preserved
            $table->date('interment_date')->nullable();
            $table->string('interment_date_raw')->nullable();

            // Lot — raw string + parsed components
            $table->string('lot')->nullable();
            $table->unsignedInteger('lot_number')->nullable();
            $table->string('lot_qualifier')->nullable();

            // Block — raw string + parsed components
            $table->string('block')->nullable();
            $table->unsignedInteger('block_number')->nullable();
            $table->string('block_suffix')->nullable();

            // Boolean flags
            $table->boolean('is_veteran')->default(false);
            $table->boolean('is_cremation')->default(false);
            $table->enum('cremation_placement', ['foot', 'head', 'full', 'only'])->nullable();
            $table->boolean('is_infant')->default(false);
            $table->boolean('is_disinterment')->default(false);

            // Notes
            $table->text('notes')->nullable();
            $table->text('source_notes_raw')->nullable();

            // Import provenance
            $table->string('import_source')->nullable();
            $table->unsignedInteger('import_row')->nullable();

            // Optional deed link
            $table->foreignId('deed_id')->nullable()->constrained()->nullOnDelete();

            // Future map overlay — accepts GPS {"lat","lng"} or relative {"x","y"} coords
            $table->json('plot_coordinates')->nullable();

            $table->timestamps();

            // Common query indexes
            $table->index(['cemetery_id', 'last_name']);
            $table->index(['cemetery_id', 'block_number', 'lot_number']);
            $table->index('is_veteran');
            $table->index('is_infant');
            $table->index('is_cremation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interments');
    }
};
