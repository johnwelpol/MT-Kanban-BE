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
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('board_id');
            $table->uuid('board_column_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'board_column_id', 'position']);
            $table->index(['tenant_id', 'assignee_id']);
            $table->foreign(['tenant_id', 'board_id'], 'cards_board_fk')
                ->references(['tenant_id', 'id'])->on('boards')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'board_id', 'board_column_id'], 'cards_column_fk')
                ->references(['tenant_id', 'board_id', 'id'])->on('board_columns')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
