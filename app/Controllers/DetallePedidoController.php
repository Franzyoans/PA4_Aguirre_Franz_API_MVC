<?php

namespace App\Controllers;

use App\Models\DetallePedido;
use App\Models\Pedido;
use Core\Request;
use Core\Response;
use PDO;

class DetallePedidoController extends BaseController
{
    protected string $entidad = 'el detalle de pedido';
    protected string $plural = 'detalles de pedido';

    private Pedido $pedidos;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->model = new DetallePedido($db);
        $this->pedidos = new Pedido($db);
    }

    protected function reglas(bool $creando): array
    {
        return $creando
            ? [
                'id_pedido'   => 'required|int|exists:pedidos,id_pedido',
                'id_producto' => 'required|int|exists:productos,id_producto',
                'cantidad'    => 'required|int|min:1',
            ]
            : ['cantidad' => 'required|int|min:1']; // al actualizar solo cambia la cantidad
    }

    /** POST: usa el procedimiento almacenado (valida stock, descuenta y recalcula total). */
    public function store(): void
    {
        $datos = Request::body();
        $this->validarOFallar($datos, true, null);

        $idPedido = (int) $datos['id_pedido'];
        $idProducto = (int) $datos['id_producto'];

        if ($this->pedidos->estado($idPedido) === 'ANULADO') {
            Response::error('No se pueden agregar productos a un pedido ANULADO.', 409);
        }
        if ($this->model->existeLinea($idPedido, $idProducto)) {
            Response::error('El producto ya está en el pedido; use PUT /api/detalle-pedido/{id} para cambiar la cantidad.', 409);
        }

        $id = $this->ejecutar(fn() => $this->model->agregar($idPedido, $idProducto, (int) $datos['cantidad']));

        Response::success($this->model->find($id), 'Producto agregado al pedido correctamente', 201);
    }

    /** PUT/PATCH: solo se puede modificar la cantidad; ajusta stock y total. */
    protected function actualizar(int $id, bool $parcial): void
    {
        $this->obtenerOFallar($id);
        $datos = Request::body();
        $this->validarOFallar($datos, false, null);

        if ($this->model->estadoPedidoDeLinea($id) === 'ANULADO') {
            Response::error('No se puede modificar el detalle de un pedido ANULADO.', 409);
        }

        $this->ejecutar(fn() => $this->model->cambiarCantidad($id, (int) $datos['cantidad']));

        Response::success($this->model->find($id), 'Cantidad actualizada correctamente');
    }

    /** DELETE: devuelve el stock y recalcula el total del pedido. */
    public function destroy(int $id): void
    {
        $this->obtenerOFallar($id);

        if ($this->model->estadoPedidoDeLinea($id) === 'ANULADO') {
            Response::error('No se puede modificar el detalle de un pedido ANULADO.', 409);
        }

        $this->ejecutar(fn() => $this->model->delete($id));

        Response::success(null, 'Línea eliminada del pedido correctamente');
    }
}
