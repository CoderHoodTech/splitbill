<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('settled_at');
            $table->timestamps();

            $table->index(['group_id', 'settled_at']);
            $table->index('from_user_id');
            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
