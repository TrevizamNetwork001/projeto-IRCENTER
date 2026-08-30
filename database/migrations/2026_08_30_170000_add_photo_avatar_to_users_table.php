<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_mode', 10)
                ->default('initials')
                ->after('avatar_key');

            $table->string('avatar_photo_path')
                ->nullable()
                ->after('avatar_mode');
        });

        // Preserva o avatar tematico de quem ja tinha escolhido um antes
        // desta coluna existir - sem isso, todo mundo cairia para
        // "iniciais" no primeiro deploy.
        DB::table('users')
            ->whereNotNull('avatar_key')
            ->update(['avatar_mode' => 'avatar']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avatar_mode', 'avatar_photo_path']);
        });
    }
};
