<?php

namespace App\Models;

class Proveedor extends BaseModel
{
    protected string $table = 'proveedores';
    protected string $pk = 'id_proveedor';
    protected array $columns = ['razon_social', 'ruc', 'contacto', 'telefono', 'correo', 'estado'];
    protected array $filterable = ['estado'];
    protected array $searchable = ['razon_social', 'ruc', 'contacto'];
    protected string $orderBy = 't.razon_social';
}
