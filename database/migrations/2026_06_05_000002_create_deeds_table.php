<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cemetery_id')->constrained()->cascadeOnDelete();
            $table->string('lot')->nullable();
            $table->string('block')->nullable();
            $table->string('grantor_name')->nullable();
            $table->string('grantee_name')->nullable();
            $table->date('deed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deeds');
    }
};
