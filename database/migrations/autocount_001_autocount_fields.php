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
        Schema::create('autocount_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->nullable();
            $table->json('data')->nullable();
            $table->string('parent_type')->nullable();
            $table->ulid('parent_id')->nullable();
            $table->timestamps();

            $table->index(['parent_type', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autocount_fields');
    }
};