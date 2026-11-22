<?php

namespace App\Controllers;

use App\Models\Proveedor;
use PDO;

class ProveedorController extends BaseController
{
    protected string $entidad = 'el proveedor';
    protected string $plural = 'proveedores';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Proveedor($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'razon_social' => 'required|string|max:100',
            'ruc'          => 'required|digits:11',
            'contacto'     => 'string|max:80',
            'telefono'     => 'string|max:15',
            'correo'       => 'email|max:100',
            'estado'       => 'in:0,1',
        ];
    }
}
