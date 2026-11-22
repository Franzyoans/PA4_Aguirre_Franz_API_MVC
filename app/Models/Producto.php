<?php

namespace App\Models;

class Producto extends BaseModel
{
    protected string $table = 'productos';
    protected string $pk = 'id_producto';
    protected array $columns = [
        'codigo', 'nombre', 'descripcion', 'precio_venta', 'stock',
        'id_categoria', 'id_proveedor', 'estado',
    ];
    protected array $filterable = ['id_categoria', 'id_proveedor', 'estado'];
    protected array $searchable = ['codigo', 'nombre', 'descripcion'];

    protected function selectSql(): string
    {
        return "SELECT t.id_producto, t.codigo, t.nombre, t.descripcion, t.precio_venta, t.stock,
                       t.id_categoria, c.nombre AS categoria,
                       t.id_proveedor, pr.razon_social AS proveedor,
                       t.estado, t.fecha_registro
                FROM productos t
                INNER JOIN categorias c   ON c.id_categoria  = t.id_categoria
                INNER JOIN proveedores pr ON pr.id_proveedor = t.id_proveedor";
    }
}
