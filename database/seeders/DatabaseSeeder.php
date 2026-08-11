<?php

namespace Database\Seeders;

use App\Models\Asiento;
use App\Models\BoletoEvento;
use App\Models\Evento;
use App\Models\Taquilla;
use App\Models\Teatro;
use App\Models\User;
use App\Models\ZonaTeatro;
use App\Services\SeatGeneratorService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        [$admin, $organizador, $taquillaVendedor, $cliente] = $this->crearUsuariosDelSistema();

        $metropolitan = $this->crearRecinto(
            $organizador,
            [
                'nombre'             => 'Teatro Metropólitan',
                'ubicacion'          => 'Av. Independencia 90, Centro, CDMX',
                'filas_totales'      => 15,
                'asientos_por_fila'  => 20,
                'pasillos_slots'     => [6, 12],
                'posicion_escenario' => 'arriba',
            ],
            [
                ['nombre_zona' => 'Platea VIP', 'nivel_proximidad' => '1x', 'fila_inicio' => 'A', 'fila_fin' => 'E'],
                ['nombre_zona' => 'Platea General', 'nivel_proximidad' => '2x', 'fila_inicio' => 'F', 'fila_fin' => 'K'],
                ['nombre_zona' => 'Balcón Alto', 'nivel_proximidad' => '3x', 'fila_inicio' => 'L', 'fila_fin' => 'O'],
            ]
        );

        $arenaNeza = $this->crearRecinto(
            $organizador,
            [
                'nombre'             => 'Arena Neza',
                'ubicacion'          => 'Av. Chimalhuácatl s/n, Nezahualcóyotl',
                'filas_totales'      => 18,
                'asientos_por_fila'  => 20,
                'pasillos_slots'     => [5, 15],
                'posicion_escenario' => 'arriba',
            ],
            [
                ['nombre_zona' => 'Pista', 'nivel_proximidad' => '1x', 'fila_inicio' => 'A', 'fila_fin' => 'F'],
                ['nombre_zona' => 'General', 'nivel_proximidad' => '2x', 'fila_inicio' => 'G', 'fila_fin' => 'N'],
                ['nombre_zona' => 'Grada Alta', 'nivel_proximidad' => '3x', 'fila_inicio' => 'O', 'fila_fin' => 'R'],
            ]
        );

        $auditorioAmtzcalli = $this->crearRecinto(
            $organizador,
            [
                'nombre'             => 'Auditorio Amtzcalli',
                'ubicacion'          => 'Av. Juárez s/n, Centro, Texcoco',
                'filas_totales'      => 12,
                'asientos_por_fila'  => 16,
                'pasillos_slots'     => [4, 10],
                'posicion_escenario' => 'arriba',
            ],
            [
                ['nombre_zona' => 'Preferente', 'nivel_proximidad' => '1x', 'fila_inicio' => 'A', 'fila_fin' => 'D'],
                ['nombre_zona' => 'General', 'nivel_proximidad' => '2x', 'fila_inicio' => 'E', 'fila_fin' => 'L'],
            ]
        );

        // Taquilla física del Teatro Metropólitan, asignada al usuario 'vendedor'.
        $taquilla = Taquilla::create([
            'teatro_id' => $metropolitan->id,
            'nombre'    => 'Taquilla Principal',
            'activa'    => true,
        ]);
        $taquillaVendedor->update([
            'organizador_padre_id' => $organizador->id,
            'taquilla_id'          => $taquilla->id,
        ]);

        $this->crearEvento(
            $metropolitan,
            [
                'titulo'      => 'Concierto Sinfónico de Otoño',
                'descripcion' => 'La Orquesta Filarmónica Metropolitana interpreta las grandes obras clásicas en una noche inolvidable.',
                'imagen_url'  => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=800&q=80',
                'categoria'   => 'Concierto',
                'fecha_hora'  => now()->addDays(20),
            ],
            [
                'Platea VIP'     => 950.00,
                'Platea General' => 650.00,
                'Balcón Alto'    => 400.00,
            ]
        );

        $this->crearEvento(
            $metropolitan,
            [
                'titulo'      => 'Obra de Teatro: El Fantasma de la Ópera',
                'descripcion' => 'Una espectacular producción en vivo llena de música, luces y drama.',
                'imagen_url'  => 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?auto=format&fit=crop&w=800&q=80',
                'categoria'   => 'Teatro',
                'fecha_hora'  => now()->addDays(35),
            ],
            [
                'Platea VIP'     => 1200.00,
                'Platea General' => 800.00,
                'Balcón Alto'    => 500.00,
            ]
        );

        $this->crearEvento(
            $arenaNeza,
            [
                'titulo'      => 'Torneo Estelar de Lucha Libre',
                'descripcion' => 'Las máximas estrellas del ring se enfrentan en una noche de lucha libre mexicana.',
                'imagen_url'  => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=800&q=80',
                'categoria'   => 'Deportes',
                'fecha_hora'  => now()->addDays(10),
            ],
            [
                'Pista'      => 850.00,
                'General'    => 550.00,
                'Grada Alta' => 300.00,
            ]
        );

        $this->crearEvento(
            $auditorioAmtzcalli,
            [
                'titulo'      => 'Conferencia Magna: Inteligencia Artificial 2026',
                'descripcion' => 'Especialistas de la industria comparten las últimas tendencias en inteligencia artificial aplicada.',
                'imagen_url'  => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=800&q=80',
                'categoria'   => 'Conferencia',
                'fecha_hora'  => now()->addDays(50),
            ],
            [
                'Preferente' => 1500.00,
                'General'    => 900.00,
            ]
        );
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User} [admin, organizador, vendedor, cliente]
     */
    private function crearUsuariosDelSistema(): array
    {
        $admin = User::create([
            'nombre'               => 'Administrador KikiiTick',
            'correo'               => 'admin@kikiitick.com',
            'contrasena'           => 'password123',
            'rol'                  => 'admin',
            'correo_verificado_at' => now(),
        ]);

        $organizador = User::create([
            'nombre'               => 'Organizador Principal',
            'correo'               => 'organizador@kikiitick.com',
            'contrasena'           => 'password123',
            'rol'                  => 'organizador',
            'estatus_organizador'  => 'aprobado',
            'correo_verificado_at' => now(),
        ]);

        $vendedor = User::create([
            'nombre'               => 'Cajero Taquilla',
            'correo'               => 'taquilla@kikiitick.com',
            'contrasena'           => 'password123',
            'rol'                  => 'vendedor',
            'correo_verificado_at' => now(),
        ]);

        $cliente = User::create([
            'nombre'               => 'Cliente Demo',
            'correo'               => 'cliente@kikiitick.com',
            'contrasena'           => 'password123',
            'rol'                  => 'cliente',
            'correo_verificado_at' => now(),
        ]);

        return [$admin, $organizador, $vendedor, $cliente];
    }

    /**
     * Crea un Teatro, genera su matriz física de asientos y configura sus zonas,
     * replicando el mismo flujo que TeatroController::store()/storeZona() para que
     * el estado quede idéntico al que produciría un organizador real desde la UI.
     */
    private function crearRecinto(User $organizador, array $datosTeatro, array $zonas): Teatro
    {
        $teatro = Teatro::create([
            'usuario_id'         => $organizador->id,
            'nombre'             => $datosTeatro['nombre'],
            'ubicacion'          => $datosTeatro['ubicacion'],
            'capacidad_total'    => $datosTeatro['filas_totales'] * $datosTeatro['asientos_por_fila'],
            'filas_totales'      => $datosTeatro['filas_totales'],
            'asientos_por_fila'  => $datosTeatro['asientos_por_fila'],
            'pasillos_slots'     => $datosTeatro['pasillos_slots'],
            'posicion_escenario' => $datosTeatro['posicion_escenario'],
        ]);

        SeatGeneratorService::generarAsientosParaTeatro($teatro);

        foreach ($zonas as $zonaDef) {
            $filasRango = range($zonaDef['fila_inicio'], $zonaDef['fila_fin']);

            $capacidadCalculada = Asiento::where('teatro_id', $teatro->id)
                ->whereIn('fila', $filasRango)
                ->where('tipo', 'asiento')
                ->count();

            $zona = ZonaTeatro::create([
                'teatro_id'          => $teatro->id,
                'nombre_zona'        => $zonaDef['nombre_zona'],
                'nivel_proximidad'   => $zonaDef['nivel_proximidad'],
                'capacidad_asientos' => $capacidadCalculada,
                'fila_inicio'        => $zonaDef['fila_inicio'],
                'fila_fin'           => $zonaDef['fila_fin'],
                'es_numerada'        => true,
            ]);

            Asiento::where('teatro_id', $teatro->id)
                ->whereIn('fila', $filasRango)
                ->update(['zona_teatro_id' => $zona->id]);
        }

        return $teatro->fresh(['zonas', 'asientos']);
    }

    /**
     * Crea un Evento público, inicializa el inventario de asientos_evento y asigna
     * la tarifa zonal (boletos_evento) indicada en $preciosPorZona (clave = nombre_zona).
     */
    private function crearEvento(Teatro $teatro, array $datosEvento, array $preciosPorZona): Evento
    {
        $evento = Evento::create([
            'teatro_id'             => $teatro->id,
            'titulo'                => $datosEvento['titulo'],
            'descripcion'           => $datosEvento['descripcion'],
            'imagen_url'            => $datosEvento['imagen_url'],
            'categoria'             => $datosEvento['categoria'],
            'fecha_hora'            => $datosEvento['fecha_hora'],
            'comision_fija_empresa' => 25.00,
            'estatus'               => 'activo',
        ]);

        SeatGeneratorService::inicializarAsientosEvento($evento);

        foreach ($teatro->zonas as $zona) {
            if (!array_key_exists($zona->nombre_zona, $preciosPorZona)) {
                continue;
            }

            BoletoEvento::create([
                'evento_id'        => $evento->id,
                'zona_teatro_id'   => $zona->id,
                'precio_base'      => $preciosPorZona[$zona->nombre_zona],
                'stock_disponible' => $zona->capacidad_asientos,
            ]);
        }

        return $evento;
    }
}
