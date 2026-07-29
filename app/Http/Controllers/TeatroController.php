<?php

namespace App\Http\Controllers;

use App\Models\Teatro;
use App\Models\ZonaTeatro;
use App\Models\Asiento;
use App\Services\SeatGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeatroController extends Controller
{
    private function verificarEstatusOrganizador($user)
    {
        if ($user->rol !== 'admin' && $user->estatus_organizador !== 'aprobado') {
            return response()->json([
                'message' => 'Tu cuenta de organizador no ha sido aprobada por un administrador.'
            ], 403);
        }
        return null;
    }

    /**
     * Un admin siempre tiene acceso; cualquier otro usuario debe ser el propietario del recurso.
     */
    private function esPropietarioOAdmin($user, int $ownerId): bool
    {
        return $user->rol === 'admin' || $user->id === $ownerId;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Teatro::with(['zonas', 'asientos']);

        if ($user->rol !== 'admin') {
            $query->where('usuario_id', $user->id);
        }

        $teatros = $query->orderBy('id', 'DESC')->get();

        return response()->json($teatros, 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($error = $this->verificarEstatusOrganizador($user)) return $error;
        
        $validated = $request->validate([
            'nombre'             => ['required', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/'],
            'ubicacion'          => 'required|string|max:255',
            'capacidad_total'    => 'required|integer|min:1',
            'filas_totales'      => 'required|integer|min:1|max:26', // A - Z
            'asientos_por_fila'  => 'required|integer|min:1',
            'pasillos_slots'     => 'nullable|array',
            'posicion_escenario' => 'required|in:arriba,centro',
        ], [
            'nombre.regex' => 'El nombre del recinto solo puede contener letras y espacios (sin números).'
        ]);

        // Validación de aforo máximo
        $sillasTotalesMatriz = $validated['filas_totales'] * $validated['asientos_por_fila'];
        if ($sillasTotalesMatriz > $validated['capacidad_total']) {
            return response()->json([
                'message' => "El total de asientos calculados en la matriz ({$sillasTotalesMatriz} sillas) sobrepasa la capacidad total del aforo ({$validated['capacidad_total']}). Reduzca las filas o asientos por fila."
            ], 422);
        }

        $teatro = Teatro::create([
            'usuario_id'         => $user->id,
            'nombre'             => trim($validated['nombre']),
            'ubicacion'          => trim($validated['ubicacion']),
            'capacidad_total'    => $validated['capacidad_total'],
            'filas_totales'      => $validated['filas_totales'],
            'asientos_por_fila'  => $validated['asientos_por_fila'],
            'pasillos_slots'     => $validated['pasillos_slots'] ?? [],
            'posicion_escenario' => $validated['posicion_escenario'],
        ]);

        // 🔹 GENERAR MATRIZ MAESTRA DEL RECINTO
        SeatGeneratorService::generarAsientosParaTeatro($teatro);

        return response()->json([
            'message' => 'Recinto y matriz física creados correctamente.',
            'teatro'  => $teatro->load(['zonas', 'asientos'])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if ($error = $this->verificarEstatusOrganizador($user)) return $error;

        $teatro = Teatro::with('zonas')->find($id);

        if (!$teatro) {
            return response()->json(['message' => 'Recinto no encontrado.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $teatro->usuario_id)) {
            return response()->json(['message' => 'No tienes permiso para editar este recinto.'], 403);
        }

        // 🛡️ RN-01: Bloquear la regeneración de la matriz si ya existe inventario comprometido
        $tieneInventarioComprometido = DB::table('asientos_evento')
            ->join('asientos', 'asientos_evento.asiento_id', '=', 'asientos.id')
            ->where('asientos.teatro_id', $teatro->id)
            ->whereIn('asientos_evento.estado', ['reservado', 'vendido'])
            ->exists();

        if ($tieneInventarioComprometido) {
            return response()->json([
                'message' => 'No se puede modificar la distribución física del recinto: existen asientos reservados o vendidos en eventos asociados. Esta acción destruiría el historial de ventas.'
            ], 409);
        }

        $validated = $request->validate([
            'nombre'             => ['required', 'string', 'max:255', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/'],
            'ubicacion'          => 'required|string|max:255',
            'capacidad_total'    => 'nullable|integer|min:1',
            'filas_totales'      => 'required|integer|min:1|max:26',
            'asientos_por_fila'  => 'required|integer|min:1',
            'pasillos_slots'     => 'nullable|array',
            'posicion_escenario' => 'required|in:arriba,centro',
        ], [
            'nombre.regex' => 'El nombre del recinto solo puede contener letras y espacios (sin números).'
        ]);

        $capacidadMaxima = $validated['capacidad_total'] ?? $teatro->capacidad_total;
        $sillasTotalesMatriz = $validated['filas_totales'] * $validated['asientos_por_fila'];

        // 🛡️ VALIDACIÓN DE AFORO
        if ($sillasTotalesMatriz > $capacidadMaxima) {
            return response()->json([
                'message' => "No se puede guardar: La matriz resultante ({$sillasTotalesMatriz} sillas) sobrepasa el aforo máximo permitido del recinto ({$capacidadMaxima} personas)."
            ], 422);
        }

        $teatro->update([
            'nombre'             => trim($validated['nombre']),
            'ubicacion'          => trim($validated['ubicacion']),
            'capacidad_total'    => $capacidadMaxima,
            'filas_totales'      => $validated['filas_totales'],
            'asientos_por_fila'  => $validated['asientos_por_fila'],
            'pasillos_slots'     => $validated['pasillos_slots'] ?? [],
            'posicion_escenario' => $validated['posicion_escenario'],
        ]);

        // 🔹 REGENERAR MATRIZ MAESTRA
        SeatGeneratorService::generarAsientosParaTeatro($teatro);

        return response()->json([
            'message' => 'Recinto y distribución física actualizados exitosamente.',
            'teatro'  => $teatro->fresh(['zonas', 'asientos'])
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $teatro = Teatro::find($id);

        if (!$teatro) {
            return response()->json(['message' => 'Recinto no encontrado.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $teatro->usuario_id)) {
            return response()->json(['message' => 'No tienes permiso para eliminar este recinto.'], 403);
        }

        $tieneEventos = DB::table('eventos')->where('teatro_id', $id)->exists();
        if ($tieneEventos) {
            return response()->json([
                'message' => 'No se puede eliminar el recinto porque tiene eventos vinculados.'
            ], 409);
        }

        $teatro->delete();

        return response()->json(['message' => 'Recinto eliminado correctamente.'], 200);
    }

    public function storeZona(Request $request, $teatroId)
    {
        $user = $request->user();
        if ($error = $this->verificarEstatusOrganizador($user)) return $error;

        $teatro = Teatro::with(['zonas', 'asientos'])->find($teatroId);

        if (!$teatro) {
            return response()->json(['message' => 'Recinto no encontrado.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $teatro->usuario_id)) {
            return response()->json(['message' => 'No tienes permiso sobre este recinto.'], 403);
        }

        $validated = $request->validate([
            'nombre_zona'      => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                Rule::unique('zonas_teatro')->where('teatro_id', $teatroId)
            ],
            'nivel_proximidad' => 'required|in:0x,1x,2x,3x,4x',
            'fila_inicio'      => 'required|string|max:5',
            'fila_fin'         => 'required|string|max:5',
            'es_numerada'      => 'nullable|boolean',
        ], [
            'nombre_zona.regex'  => 'El nombre de la zona solo puede contener letras y espacios.',
            'nombre_zona.unique' => 'Ya existe una zona con este nombre en el recinto.'
        ]);

        $filaInicio = strtoupper(trim($validated['fila_inicio']));
        $filaFin    = strtoupper(trim($validated['fila_fin']));

        if ($filaInicio > $filaFin) {
            return response()->json([
                'message' => "La fila inicial ('{$filaInicio}') no puede ser mayor que la fila final ('{$filaFin}')."
            ], 422);
        }

        $filasRango = range($filaInicio, $filaFin);

        $asientosOcupados = Asiento::where('teatro_id', $teatroId)
            ->whereIn('fila', $filasRango)
            ->whereNotNull('zona_teatro_id')
            ->exists();

        if ($asientosOcupados) {
            return response()->json([
                'message' => 'Una o más filas del rango seleccionado ya están asignadas a otra zona.'
            ], 422);
        }

        $asientosEnRango = Asiento::where('teatro_id', $teatroId)
            ->whereIn('fila', $filasRango)
            ->get();

        if ($asientosEnRango->isEmpty()) {
            return response()->json([
                'message' => 'Las filas seleccionadas no existen en el recinto.'
            ], 422);
        }

        $capacidadCalculada = $asientosEnRango->where('tipo', 'asiento')->count();

        $zona = null;

        DB::transaction(function () use ($teatroId, $validated, $filaInicio, $filaFin, $filasRango, $capacidadCalculada, &$zona) {
            $zona = ZonaTeatro::create([
                'teatro_id'          => $teatroId,
                'nombre_zona'        => trim($validated['nombre_zona']),
                'nivel_proximidad'   => $validated['nivel_proximidad'],
                'capacidad_asientos' => $capacidadCalculada,
                'fila_inicio'        => $filaInicio,
                'fila_fin'           => $filaFin,
                'es_numerada'        => $validated['es_numerada'] ?? true,
            ]);

            Asiento::where('teatro_id', $teatroId)
                ->whereIn('fila', $filasRango)
                ->update(['zona_teatro_id' => $zona->id]);
        });

        return response()->json([
            'message' => 'Zona configurada y asientos asignados correctamente.',
            'teatro'  => $teatro->fresh(['zonas', 'asientos'])
        ], 201);
    }

    public function destroyZona(Request $request, $id)
    {
        $user = $request->user();
        $zona = ZonaTeatro::with('teatro')->find($id);

        if (!$zona) {
            return response()->json(['message' => 'Zona no encontrada.'], 404);
        }

        if (!$this->esPropietarioOAdmin($user, $zona->teatro->usuario_id)) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta zona.'], 403);
        }

        $tieneBoletos = DB::table('boletos_evento')->where('zona_teatro_id', $id)->exists();
        if ($tieneBoletos) {
            return response()->json([
                'message' => 'No se puede eliminar esta zona porque ya tiene boletos configurados en eventos.'
            ], 409);
        }

        $teatroId = $zona->teatro_id;

        DB::transaction(function () use ($zona) {
            Asiento::where('zona_teatro_id', $zona->id)->update(['zona_teatro_id' => null]);
            $zona->delete();
        });

        $teatro = Teatro::with(['zonas', 'asientos'])->find($teatroId);

        return response()->json([
            'message' => 'Zona eliminada y filas liberadas correctamente.',
            'teatro'  => $teatro
        ], 200);
    }
}