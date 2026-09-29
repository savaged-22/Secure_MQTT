<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnstiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();               // ej: T-BIB-IN-01
            $table->string('direction')->default('in');     // in | out
            $table->string('status')->default('active');    // active | inactive | maintenance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnstiles');
    }
};
