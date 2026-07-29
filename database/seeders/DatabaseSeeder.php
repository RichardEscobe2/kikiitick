<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear un usuario Organizador (sin pasar campos de fecha manualmente)
        $usuarioId = DB::table('usuarios')->insertGetId([
            'nombre' => 'Organizador Principal',
            'correo' => 'organizador@kikiitick.com',
            'contrasena' => Hash::make('password123'),
            'rol' => 'organizador',
            'estatus_organizador' => 'aprobado',
        ]);

        // 2. Crear el Teatro
        $teatroId = DB::table('teatros')->insertGetId([
            'usuario_id' => $usuarioId,
            'nombre' => 'Teatro Gran Recinto',
            'ubicacion' => 'Av. Benito Juárez 123, Centro',
            'capacidad_total' => 500,
        ]);

        // 3. Crear Zonas
        $zonaVIPId = DB::table('zonas_teatro')->insertGetId([
            'teatro_id' => $teatroId,
            'nombre_zona' => 'VIP',
            'nivel_proximidad' => '1x',
            'capacidad_asientos' => 100,
        ]);

        $zonaGeneralId = DB::table('zonas_teatro')->insertGetId([
            'teatro_id' => $teatroId,
            'nombre_zona' => 'General',
            'nivel_proximidad' => '2x',
            'capacidad_asientos' => 400,
        ]);

        // 4. Crear Eventos
        $evento1Id = DB::table('eventos')->insertGetId([
            'teatro_id' => $teatroId,
            'titulo' => 'Obra de Teatro: El Fantasma de la Ópera',
            'descripcion' => 'Una espectacular producción en vivo llena de música, luces y drama.',
            'imagen_url' => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?auto=format&fit=crop&w=800&q=80',
            'categoria' => 'Teatro',
            'fecha_hora' => now()->addDays(10),
            'comision_fija_empresa' => 25.00,
            'estatus' => 'activo',
        ]);

        $evento2Id = DB::table('eventos')->insertGetId([
            'teatro_id' => $teatroId,
            'titulo' => 'Concierto Sinfónico de Primavera',
            'descripcion' => 'Disfruta de las mejores piezas clásicas interpretadas por la orquesta filarmónica.',
            'imagen_url' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=800&q=80',
            'categoria' => 'Concierto',
            'fecha_hora' => now()->addDays(20),
            'comision_fija_empresa' => 30.00,
            'estatus' => 'activo',
        ]);

        // 5. Crear Boletos por Evento
        DB::table('boletos_evento')->insert([
            [
                'evento_id' => $evento1Id,
                'zona_teatro_id' => $zonaVIPId,
                'precio_base' => 850.00,
                'stock_disponible' => 100,
            ],
            [
                'evento_id' => $evento1Id,
                'zona_teatro_id' => $zonaGeneralId,
                'precio_base' => 450.00,
                'stock_disponible' => 400,
            ],
            [
                'evento_id' => $evento2Id,
                'zona_teatro_id' => $zonaVIPId,
                'precio_base' => 1200.00,
                'stock_disponible' => 100,
            ],
        ]);
    }
}