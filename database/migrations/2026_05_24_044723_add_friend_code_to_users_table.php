<?php

declare(strict_types=1);

use App\Actions\Users\GenerateFriendCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('friend_code', 16)->nullable()->after('email');
        });

        $generator = app(GenerateFriendCode::class);
        DB::table('users')->whereNull('friend_code')->orderBy('id')->each(function ($row) use ($generator) {
            DB::table('users')->where('id', $row->id)->update([
                'friend_code' => $generator->execute(),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('friend_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['friend_code']);
            $table->dropColumn('friend_code');
        });
    }
};
