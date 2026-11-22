<?php

namespace App\Controllers;

use App\Models\Reporte;
use Core\Response;
use PDO;

class ReporteController
{
    private Reporte $reporte;

    public function __construct(PDO $db)
    {
        $this->reporte = new Reporte($db);
    }

    public function ventasPorCategoria(): void
    {
        Response::success($this->reporte->ventasPorCategoria(), 'Reporte de ventas por categoría');
    }

    public function pedidosResumen(): void
    {
        $estado = $_GET['estado'] ?? null;

        if ($estado !== null && !in_array($estado, ['PENDIENTE', 'PAGADO', 'ENVIADO', 'ANULADO'], true)) {
            Response::error('El estado debe ser PENDIENTE, PAGADO, ENVIADO o ANULADO.', 400);
        }

        Response::success($this->reporte->pedidosResumen($estado), 'Resumen de pedidos');
    }
}
