<?php

namespace App\Controllers;

use App\Models\Usuario;
use PDO;

class UsuarioController extends BaseController
{
    protected string $entidad = 'el usuario';
    protected string $plural = 'usuarios';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Usuario($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'correo' => 'required|email|max:100',
            // La clave es obligatoria al crear; al actualizar solo si se desea cambiar
            'clave'  => ($creando ? 'required|' : '') . 'string|min:6|max:50',
            'rol'    => 'in:ADMIN,VENDEDOR,ALMACEN',
            'estado' => 'in:0,1',
        ];
    }

    /**
     * La clave nunca se guarda ni se devuelve en texto plano.
     * Se usa SHA-256 para ser coherente con el script SQL (CHAR(64)).
     * En un sistema real se recomienda password_hash() de PHP.
     */
    protected function preparar(array $datos): array
    {
        if (isset($datos['clave'])) {
            $datos['clave_hash'] = hash('sha256', $datos['clave']);
        }
        unset($datos['clave']);

        return $datos;
    }
}
