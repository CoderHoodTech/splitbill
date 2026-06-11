<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored as two rows per relationship: (A, B) and (B, A). Trades a bit of
     * extra storage for simple bidirectional queries via Eloquent relations.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'friend_id']);
            $table->index('friend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
