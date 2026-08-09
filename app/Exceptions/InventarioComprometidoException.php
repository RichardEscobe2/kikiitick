<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción de dominio (RN-01) lanzada cuando se intenta regenerar/destruir la matriz
 * física de asientos de un recinto que tiene asientos en estado 'reservado' o 'vendido'
 * en algún evento asociado. Su mensaje es siempre seguro para mostrar directamente al
 * cliente vía API.
 */
class InventarioComprometidoException extends Exception
{
    //
}
