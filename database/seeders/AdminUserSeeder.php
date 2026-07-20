<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('IRCENTER_ADMIN_NAME', 'Administrador IRCENTER');
        $email = env('IRCENTER_ADMIN_EMAIL');
        $password = env('IRCENTER_ADMIN_PASSWORD');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException(
                'Defina IRCENTER_ADMIN_EMAIL antes de executar o seeder.'
            );
        }

        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException(
                'Defina IRCENTER_ADMIN_PASSWORD com pelo menos 12 caracteres.'
            );
        }

        User::updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'active' => true,
            ],
        );
    }
}
