<?php

namespace Core;

class Request
{
    /**
     * Devuelve el cuerpo JSON como arreglo. Si el JSON es inválido responde 400.
     */
    public static function body(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Response::error('El cuerpo de la petición no es un JSON válido.', 400);
        }

        return $data;
    }
}
