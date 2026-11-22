<?php

namespace App\Models;

use PDO;

/** Consultas de reporte (usan el procedimiento almacenado y la vista del script SQL). */
class Reporte
{
    public function __construct(private PDO $db)
    {
    }

    public function ventasPorCategoria(): array
    {
        $stmt = $this->db->prepare("CALL sp_ventas_por_categoria()");
        $stmt->execute();
        $filas = $stmt->fetchAll();
        $stmt->closeCursor();

        return $filas;
    }

    public function pedidosResumen(?string $estado = null): array
    {
        $sql = "SELECT * FROM vw_pedidos_resumen";
        $params = [];
        if ($estado !== null && $estado !== '') {
            $sql .= " WHERE estado = :e";
            $params[':e'] = $estado;
        }
        $stmt = $this->db->prepare($sql . " ORDER BY fecha DESC");
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
