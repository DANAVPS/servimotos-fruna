<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Por defecto: Administradora de un taller nuevo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'taller_id' => Taller::factory(),
            'role_id' => fn () => $this->rol(Rol::ADMINISTRADORA),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function administradora(): static
    {
        return $this->state(fn () => ['role_id' => $this->rol(Rol::ADMINISTRADORA)]);
    }

    public function mecanico(): static
    {
        return $this->state(fn () => ['role_id' => $this->rol(Rol::MECANICO)]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'taller_id' => null,
            'role_id' => $this->rol(Rol::SUPERADMIN),
        ]);
    }

    public function conPin(string $pin = '482915'): static
    {
        return $this->state(fn () => ['pin_autorizacion' => $pin]);
    }

    public function deTaller(Taller $taller): static
    {
        return $this->state(fn () => ['taller_id' => $taller->id]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function rol(string $slug): int
    {
        $nombres = [
            Rol::SUPERADMIN => 'Super Administrador',
            Rol::ADMINISTRADORA => 'Administradora',
            Rol::MECANICO => 'Mecánico',
        ];

        return Rol::firstOrCreate(['slug' => $slug], ['nombre' => $nombres[$slug]])->id;
    }
}
