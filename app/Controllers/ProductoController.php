<?php

namespace App\Controllers;

use App\Models\Producto;
use PDO;

class ProductoController extends BaseController
{
    protected string $entidad = 'el producto';
    protected string $plural = 'productos';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Producto($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'codigo'       => 'required|string|max:20',
            'nombre'       => 'required|string|max:120',
            'descripcion'  => 'string|max:255',
            'precio_venta' => 'required|numeric|min:0.01',
            'stock'        => 'int|min:0',
            'id_categoria' => 'required|int|exists:categorias,id_categoria',
            'id_proveedor' => 'required|int|exists:proveedores,id_proveedor',
            'estado'       => 'in:0,1',
        ];
    }
}
