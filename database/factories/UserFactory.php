<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre'               => fake()->name(),
            'correo'               => fake()->unique()->safeEmail(),
            'contrasena'           => static::$password ??= Hash::make('password'),
            'rol'                  => 'cliente',
            'estatus_organizador'  => 'ninguno',
            'correo_verificado_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['rol' => 'admin']);
    }

    public function organizador(): static
    {
        return $this->state(fn (array $attributes) => [
            'rol'                 => 'organizador',
            'estatus_organizador' => 'aprobado',
        ]);
    }

    public function estatusOrganizador(string $estatus): static
    {
        return $this->state(fn (array $attributes) => ['estatus_organizador' => $estatus]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'correo_verificado_at' => null,
        ]);
    }
}
