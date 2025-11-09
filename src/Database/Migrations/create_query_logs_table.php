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
        Schema::create('query_logs', function (Blueprint $table) {
            $table->id();
            
            // Flexible user_id column - works with UUID or bigInteger
            // Check if users table exists and use appropriate type
            if (Schema::hasTable('users')) {
                $userIdColumn = Schema::getColumnType('users', 'id');
                
                if (in_array($userIdColumn, ['char', 'uuid', 'string'])) {
                    // UUID-based users table
                    $table->uuid('user_id')->nullable();
                } else {
                    // Auto-increment (bigInteger) users table
                    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                }
            } else {
                // Fallback: use string type if users table doesn't exist yet
                $table->string('user_id', 36)->nullable();
            }
            
            $table->text('query');
            $table->string('intent')->nullable(); // sql, rag, hybrid
            $table->text('generated_sql')->nullable();
            $table->json('retrieved_tables')->nullable();
            $table->json('result')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('intent');
            $table->index('success');
            $table->index('created_at');
        });
        
        // Add foreign key constraint separately if users table exists (for UUID case)
        if (Schema::hasTable('users')) {
            $userIdColumn = Schema::getColumnType('users', 'id');
            
            if (in_array($userIdColumn, ['char', 'uuid', 'string'])) {
                Schema::table('query_logs', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_logs');
    }
};
