<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('used_nonces', function (Blueprint $table) {
            $table->string('nonce', 64)->primary();     // PK = anti-replay atómico
            $table->timestamp('expires_at')->index();   // para limpiar los vencidos
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('used_nonces');
    }
};
