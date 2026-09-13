<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $taller = Taller::first();
        $rolAdmin = Rol::where('slug', Rol::ADMINISTRADORA)->first();
        $rolMecanico = Rol::where('slug', Rol::MECANICO)->first();

        User::firstOrCreate(
            ['email' => 'admin@servimotosfruna.com'],
            [
                'taller_id' => $taller->id,
                'role_id' => $rolAdmin->id,
                'name' => 'Administradora Fruna',
                'password' => Hash::make('password'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'mecanico@servimotosfruna.com'],
            [
                'taller_id' => $taller->id,
                'role_id' => $rolMecanico->id,
                'name' => 'Alexánder Duarte Molina',
                'password' => Hash::make('password'),
            ]
        );
    }
}
