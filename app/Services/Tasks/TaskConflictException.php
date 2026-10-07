<?php

namespace App\Services\Tasks;

use RuntimeException;

/** La nota cambió (en Obsidian) desde que se cargó y el cambio pisaría ese contenido. */
class TaskConflictException extends RuntimeException
{
}
