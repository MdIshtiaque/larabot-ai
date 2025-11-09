<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schema_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('table_name')->unique();
            $table->json('columns');
            $table->text('summary');
            $table->json('relationships')->nullable();
            $table->json('embedding');
            $table->timestamps();

            $table->index('table_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_embeddings');
    }
};
