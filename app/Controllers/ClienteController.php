<?php

namespace App\Controllers;

use App\Models\Cliente;
use PDO;

class ClienteController extends BaseController
{
    protected string $entidad = 'el cliente';
    protected string $plural = 'clientes';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Cliente($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'tipo_cliente' => 'required|in:PERSONA,EMPRESA',
            'nombre'       => 'required|string|max:100',
            'documento'    => 'required|digits_between:8,11',
            'correo'       => 'email|max:100',
            'telefono'     => 'string|max:15',
            'direccion'    => 'string|max:150',
        ];
    }

    /** PERSONA usa DNI (8 dígitos) y EMPRESA usa RUC (11 dígitos). */
    protected function validacionExtra(array $datos, bool $creando, ?array $actual): array
    {
        $tipo = $datos['tipo_cliente'] ?? ($actual['tipo_cliente'] ?? null);
        $doc = $datos['documento'] ?? ($actual['documento'] ?? null);

        if ($tipo && $doc && ctype_digit((string) $doc)) {
            if ($tipo === 'PERSONA' && strlen((string) $doc) !== 8) {
                return ['documento' => 'Para una PERSONA el documento debe ser un DNI de 8 dígitos.'];
            }
            if ($tipo === 'EMPRESA' && strlen((string) $doc) !== 11) {
                return ['documento' => 'Para una EMPRESA el documento debe ser un RUC de 11 dígitos.'];
            }
        }

        return [];
    }
}
