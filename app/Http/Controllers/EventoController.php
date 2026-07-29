<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Teatro;
use App\Models\ZonaTeatro;
use App\Models\BoletoEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventoController extends Controller
{
    /**
     * Obtener los eventos creados en los recintos del organizador autenticado
     */
    public function index(Request $request)
    {
        $usuario = $request->user();

        $eventos = Evento::with(['teatro.zonas', 'boletosEvento.zonaTeatro'])
            ->whereHas('teatro', function ($query) use ($usuario) {
                $query->where('usuario_id', $usuario->id);
            })
            ->orderBy('fecha_hora', 'asc')
            ->get();

        return response()->json($eventos, 200);
    }

    public function store(Request $request)
    {
        $usuario = $request->user();

        $validated = $request->validate([
            'teatro_id'             => 'required|exists:teatros,id',
            'titulo'                => 'required|string|max:255',
            'descripcion'           => 'nullable|string',
            'imagen'                => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'imagen_url'            => 'nullable|string|max:500',
            'categoria'             => 'required|string|max:50',
            'fecha_hora'            => 'required|date|after:now',
            'comision_fija_empresa' => 'nullable|numeric|min:0',
            'estatus'               => 'nullable|in:borrador,activo,finalizado'
        ], [
            'fecha_hora.after' => 'La fecha y hora del evento debe ser posterior a la fecha actual.',
            'teatro_id.exists' => 'El recinto seleccionado no existe.',
            'imagen.image'     => 'El archivo debe ser una imagen válida (jpg, png, webp).',
            'imagen.max'       => 'La imagen no puede pesar más de 4MB.'
        ]);

        $teatro = Teatro::where('id', $validated['teatro_id'])
            ->where('usuario_id', $usuario->id)
            ->first();

        if (!$teatro) {
            return response()->json(['message' => 'No tienes permisos para crear un evento en este recinto.'], 403);
        }

        $urlFinalImagen = $validated['imagen_url'] ?? null;

        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('eventos', 'public');
            $urlFinalImagen = asset('storage/' . $path);
        }

        $evento = Evento::create([
            'teatro_id'             => $validated['teatro_id'],
            'titulo'                => $validated['titulo'],
            'descripcion'           => $validated['descripcion'] ?? null,
            'imagen_url'            => $urlFinalImagen,
            'categoria'             => $validated['categoria'],
            'fecha_hora'            => $validated['fecha_hora'],
            'comision_fija_empresa' => $validated['comision_fija_empresa'] ?? 0.00,
            'estatus'               => $validated['estatus'] ?? 'activo',
        ]);

        return response()->json([
            'message' => 'Evento creado exitosamente.',
            'evento'  => $evento->load(['teatro.zonas', 'boletosEvento'])
        ], 201);
    }

    /**
     * Actualizar datos de un evento
     */
    public function update(Request $request, $id)
    {
        $usuario = $request->user();

        $evento = Evento::whereHas('teatro', function ($query) use ($usuario) {
            $query->where('usuario_id', $usuario->id);
        })->find($id);

        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado o no autorizado.'], 404);
        }

        $validated = $request->validate([
            'teatro_id'   => 'required|exists:teatros,id',
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'imagen_url'  => 'nullable|string|max:500',
            'categoria'   => 'required|string|max:50',
            'fecha_hora'  => 'required|date',
            'estatus'     => 'required|in:borrador,activo,finalizado'
        ]);

        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('eventos', 'public');
            $validated['imagen_url'] = asset('storage/' . $path);
        }

        unset($validated['imagen']);

        $evento->update($validated);

        return response()->json([
            'message' => 'Evento actualizado correctamente.',
            'evento'  => $evento->load(['teatro.zonas', 'boletosEvento'])
        ], 200);
    }

    /**
     * Eliminar un evento
     */
    public function destroy(Request $request, $id)
    {
        $usuario = $request->user();

        $evento = Evento::whereHas('teatro', function ($query) use ($usuario) {
            $query->where('usuario_id', $usuario->id);
        })->find($id);

        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado o no autorizado.'], 404);
        }

        $evento->delete();

        return response()->json(['message' => 'Evento eliminado correctamente.'], 200);
    }

    /**
     * Obtener la lista de zonas con sus precios asignados para un evento
     */
    public function getPrecios(Request $request, $id)
    {
        $usuario = $request->user();

        $evento = Evento::with(['teatro.zonas', 'boletosEvento'])
            ->whereHas('teatro', function ($query) use ($usuario) {
                $query->where('usuario_id', $usuario->id);
            })->find($id);

        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado o no autorizado.'], 404);
        }

        $zonasConPrecio = $evento->teatro->zonas->map(function ($zona) use ($evento) {
            $boleto = $evento->boletosEvento->firstWhere('zona_teatro_id', $zona->id);

            return [
                'zona_teatro_id'    => $zona->id,
                'nombre_zona'       => $zona->nombre_zona,
                'nivel_proximidad'  => $zona->nivel_proximidad,
                'capacidad_asientos'=> $zona->capacidad_asientos,
                'precio_base'       => $boleto ? $boleto->precio_base : 0.00,
                'stock_disponible'  => $boleto ? $boleto->stock_disponible : $zona->capacidad_asientos,
            ];
        });

        return response()->json([
            'evento_id' => $evento->id,
            'titulo'    => $evento->titulo,
            'teatro'    => $evento->teatro->nombre,
            'precios'   => $zonasConPrecio
        ], 200);
    }

    /**
     * Guardar/Actualizar los precios por zona de un evento
     */
    public function guardarPrecios(Request $request, $id)
    {
        $usuario = $request->user();

        $evento = Evento::whereHas('teatro', function ($query) use ($usuario) {
            $query->where('usuario_id', $usuario->id);
        })->find($id);

        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado o no autorizado.'], 404);
        }

        $validated = $request->validate([
            'precios'                  => 'required|array|min:1',
            'precios.*.zona_teatro_id' => 'required|exists:zonas_teatro,id',
            'precios.*.precio_base'    => 'required|numeric|min:0',
        ], [
            'precios.*.precio_base.min' => 'El precio de las zonas no puede ser negativo.'
        ]);

        foreach ($validated['precios'] as $item) {
            $boleto = BoletoEvento::where('evento_id', $evento->id)
                ->where('zona_teatro_id', $item['zona_teatro_id'])
                ->first();

            if ($boleto) {
                $boleto->update([
                    'precio_base' => $item['precio_base']
                ]);
            } else {
                $zona = ZonaTeatro::find($item['zona_teatro_id']);
                BoletoEvento::create([
                    'evento_id'        => $evento->id,
                    'zona_teatro_id'   => $item['zona_teatro_id'],
                    'precio_base'      => $item['precio_base'],
                    'stock_disponible' => $zona ? $zona->capacidad_asientos : 0,
                ]);
            }
        }

        return response()->json([
            'message' => 'Precios por zona actualizados correctamente.',
            'evento'  => $evento->load(['teatro.zonas', 'boletosEvento'])
        ], 200);
    }

    /**
     * GET /api/eventos/{id}/mapa
     * FASE 3: Obtener estructura completa de mapa unificado del recinto con sus asientos, tarifas y estados en tiempo real
     */
    public function getMapaEvento($id)
    {
        $evento = Evento::with([
            'teatro.zonas',
            'teatro.asientos',
            'boletosEvento',
            'asientosEvento'
        ])->find($id);

        if (!$evento || $evento->estatus !== 'activo') {
            return response()->json(['message' => 'El evento no se encuentra activo o no existe.'], 404);
        }

        // Mapear tarifas por zona
        $tarifasMap = $evento->boletosEvento->pluck('precio_base', 'zona_teatro_id');

        // Mapear estado dinámico de asientos para el evento (disponible, reservado, bloqueado, vendido)
        $estadoAsientosMap = $evento->asientosEvento->pluck('estado', 'asiento_id');

        // Indizar zonas por ID para cruce rápido
        $zonasById = $evento->teatro->zonas->keyBy('id');

        // Mapear la lista de zonas con sus tarifas
        $zonasResumen = $evento->teatro->zonas->map(function ($zona) use ($tarifasMap) {
            return [
                'id'                 => $zona->id,
                'nombre_zona'        => $zona->nombre_zona,
                'nivel_proximidad'   => $zona->nivel_proximidad,
                'precio_base'        => (float) $tarifasMap->get($zona->id, 0.00),
                'capacidad_asientos' => $zona->capacidad_asientos,
                'fila_inicio'        => $zona->fila_inicio,
                'fila_fin'           => $zona->fila_fin,
            ];
        });

        // FASE 3: Mapear la MATRIZ COMPLETA DE ASIENTOS del recinto
        $asientosMaster = $evento->teatro->asientos->map(function ($asiento) use ($estadoAsientosMap, $tarifasMap, $zonasById) {
            $esPasillo = $asiento->tipo === 'pasillo';
            $estadoEvento = $estadoAsientosMap->get($asiento->id, 'disponible');
            $zona = $asiento->zona_teatro_id ? $zonasById->get($asiento->zona_teatro_id) : null;
            $precio = $asiento->zona_teatro_id ? $tarifasMap->get($asiento->zona_teatro_id, 0.00) : 0.00;

            return [
                'id'               => $asiento->id,
                'zona_teatro_id'   => $asiento->zona_teatro_id,
                'nombre_zona'      => $zona ? $zona->nombre_zona : 'Sin Zona',
                'nivel_proximidad' => $zona ? $zona->nivel_proximidad : 'N/A',
                'precio_base'      => (float) $precio,
                'fila'             => $asiento->fila,
                'numero'           => $asiento->numero,
                'etiqueta'         => $asiento->codigo,
                'es_pasillo'       => $esPasillo,
                'slot_index'       => $asiento->slot_index,
                'estatus'          => $esPasillo ? 'pasillo' : $estadoEvento
            ];
        });

        return response()->json([
            'evento' => [
                'id'                    => $evento->id,
                'titulo'                => $evento->titulo,
                'descripcion'           => $evento->descripcion,
                'categoria'             => $evento->categoria,
                'imagen_url'            => $evento->imagen_url,
                'fecha_hora'            => $evento->fecha_hora,
                'comision_fija_empresa' => (float) $evento->comision_fija_empresa,
            ],
            'teatro' => [
                'id'                 => $evento->teatro->id,
                'nombre'             => $evento->teatro->nombre,
                'ubicacion'          => $evento->teatro->ubicacion,
                'filas_totales'      => $evento->teatro->filas_totales,
                'asientos_por_fila'  => $evento->teatro->asientos_por_fila,
                'pasillos_slots'     => $evento->teatro->pasillos_slots,
                'posicion_escenario' => $evento->teatro->posicion_escenario,
            ],
            'zonas'    => $zonasResumen,
            'asientos' => $asientosMaster
        ], 200);
    }
}