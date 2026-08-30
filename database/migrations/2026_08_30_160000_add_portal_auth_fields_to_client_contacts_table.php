<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_contacts', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('phone');
            $table->rememberToken()->after('password');
            $table->boolean('must_change_password')
                ->default(true)
                ->after('remember_token');
            $table->timestamp('password_changed_at')
                ->nullable()
                ->after('must_change_password');
        });

        // Um e-mail so pode ser identidade de login de um unico contato.
        // Contatos sem senha (a imensa maioria hoje) ficam fora da
        // restricao, entao o mesmo e-mail ainda pode se repetir entre
        // contatos que nunca vao logar (comportamento atual preservado).
        DB::statement(
            'create unique index client_contacts_login_email_unique '.
            'on client_contacts (email) where password is not null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists client_contacts_login_email_unique');

        Schema::table('client_contacts', function (Blueprint $table): void {
            $table->dropColumn([
                'password',
                'remember_token',
                'must_change_password',
                'password_changed_at',
            ]);
        });
    }
};
