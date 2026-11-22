<?php

namespace App\Models;

class Usuario extends BaseModel
{
    protected string $table = 'usuarios';
    protected string $pk = 'id_usuario';
    protected array $columns = ['nombre', 'correo', 'clave_hash', 'rol', 'estado'];
    protected array $filterable = ['rol', 'estado'];
    protected array $searchable = ['nombre', 'correo'];

    /** Nunca se devuelve la columna clave_hash. */
    protected function selectSql(): string
    {
        return "SELECT t.id_usuario, t.nombre, t.correo, t.rol, t.estado FROM usuarios t";
    }
}
