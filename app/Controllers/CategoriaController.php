<?php

namespace App\Controllers;

use App\Models\Categoria;
use PDO;

class CategoriaController extends BaseController
{
    protected string $entidad = 'la categoría';
    protected string $plural = 'categorías';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Categoria($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'nombre'      => 'required|string|max:60',
            'descripcion' => 'string|max:150',
            'estado'      => 'in:0,1',
        ];
    }
}
