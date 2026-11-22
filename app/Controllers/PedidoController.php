<?php

namespace App\Controllers;

use App\Models\Pedido;
use Core\Response;
use PDO;

class PedidoController extends BaseController
{
    protected string $entidad = 'el pedido';
    protected string $plural = 'pedidos';

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new Pedido($db);
    }

    protected function reglas(bool $creando): array
    {
        return [
            'id_cliente' => 'required|int|exists:clientes,id_cliente',
            'id_usuario' => 'required|int|exists:usuarios,id_usuario',
            'fecha'      => 'datetime',
            'estado'     => 'in:PENDIENTE,PAGADO,ENVIADO,ANULADO',
        ];
    }

    /** Un pedido anulado no puede volver a activarse. */
    protected function validacionExtra(array $datos, bool $creando, ?array $actual): array
    {
        if ($actual && $actual['estado'] === 'ANULADO'
            && isset($datos['estado']) && $datos['estado'] !== 'ANULADO') {
            Response::error('Un pedido ANULADO no puede cambiar de estado.', 409);
        }

        return [];
    }

    /** El detalle de un pedido incluye sus líneas. */
    public function show(int $id): void
    {
        $pedido = $this->obtenerOFallar($id);
        $pedido['detalle'] = $this->model->detalles($id);

        Response::success($pedido, 'Consulta de pedido realizada correctamente');
    }
}
