<?php

namespace App\Models;

class Cliente extends BaseModel
{
    protected string $table = 'clientes';
    protected string $pk = 'id_cliente';
    protected array $columns = ['tipo_cliente', 'nombre', 'documento', 'correo', 'telefono', 'direccion'];
    protected array $filterable = ['tipo_cliente', 'documento'];
    protected array $searchable = ['nombre', 'documento', 'correo'];
    protected string $orderBy = 't.nombre';
}
