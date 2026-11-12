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
        Schema::create('board_columns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('board_id');
            $table->string('name', 60);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'board_id', 'id']);
            $table->index(['tenant_id', 'board_id', 'position']);
            $table->foreign(['tenant_id', 'board_id'], 'board_columns_board_fk')
                ->references(['tenant_id', 'id'])->on('boards')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
