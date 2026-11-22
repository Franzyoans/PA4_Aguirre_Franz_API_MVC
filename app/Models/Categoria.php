<?php

namespace App\Models;

class Categoria extends BaseModel
{
    protected string $table = 'categorias';
    protected string $pk = 'id_categoria';
    protected array $columns = ['nombre', 'descripcion', 'estado'];
    protected array $filterable = ['estado'];
    protected array $searchable = ['nombre', 'descripcion'];
    protected string $orderBy = 't.nombre';
}
