<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('total_amount');
            $table->enum('split_method', ['equal', 'exact', 'percent']);
            $table->date('expense_date');
            $table->timestamps();

            $table->index(['group_id', 'expense_date']);
            $table->index('payer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
