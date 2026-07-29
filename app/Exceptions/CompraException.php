<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción de dominio para fallas esperadas del flujo de reserva/compra de boletos
 * (asiento no disponible, reserva expirada, zona sin tarifa, evento no activo, etc.).
 * Su mensaje es siempre seguro para mostrar directamente al cliente vía API.
 */
class CompraException extends Exception
{
    //
}
