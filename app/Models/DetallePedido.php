<?php

namespace App\Models;

use DomainException;
use Throwable;

class DetallePedido extends BaseModel
{
    protected string $table = 'detalle_pedido';
    protected string $pk = 'id_detalle';
    protected array $columns = ['id_pedido', 'id_producto', 'cantidad'];
    protected array $filterable = ['id_pedido', 'id_producto'];

    protected function selectSql(): string
    {
        return "SELECT t.id_detalle, t.id_pedido, t.id_producto, p.codigo, p.nombre AS producto,
                       t.cantidad, t.precio_unitario, t.subtotal
                FROM detalle_pedido t
                INNER JOIN productos p ON p.id_producto = t.id_producto";
    }

    /** ¿El producto ya está en el pedido? (clave única id_pedido + id_producto) */
    public function existeLinea(int $idPedido, int $idProducto): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM detalle_pedido WHERE id_pedido = :a AND id_producto = :b"
        );
        $stmt->execute([':a' => $idPedido, ':b' => $idProducto]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crea la línea usando el procedimiento almacenado sp_agregar_detalle_pedido
     * (valida stock, descuenta inventario y recalcula el total del pedido).
     */
    public function agregar(int $idPedido, int $idProducto, int $cantidad): int
    {
        $stmt = $this->db->prepare("CALL sp_agregar_detalle_pedido(:p, :pr, :c)");
        $stmt->execute([':p' => $idPedido, ':pr' => $idProducto, ':c' => $cantidad]);
        $stmt->closeCursor();

        $stmt = $this->db->prepare(
            "SELECT id_detalle FROM detalle_pedido WHERE id_pedido = :p AND id_producto = :pr"
        );
        $stmt->execute([':p' => $idPedido, ':pr' => $idProducto]);

        return (int) $stmt->fetchColumn();
    }

    /** Cambia la cantidad de una línea ajustando el stock y el total del pedido. */
    public function cambiarCantidad(int $id, int $nueva): void
    {
        $this->db->beginTransaction();
        try {
            $linea = $this->bloquearLinea($id);
            $producto = $this->bloquearProducto((int) $linea['id_producto']);

            $diferencia = $nueva - (int) $linea['cantidad'];
            if ($diferencia > (int) $producto['stock']) {
                throw new DomainException('Stock insuficiente para aumentar la cantidad.');
            }

            $this->db->prepare(
                "UPDATE detalle_pedido SET cantidad = :c, subtotal = precio_unitario * :c2 WHERE id_detalle = :id"
            )->execute([':c' => $nueva, ':c2' => $nueva, ':id' => $id]);

            $this->db->prepare("UPDATE productos SET stock = stock - :d WHERE id_producto = :p")
                ->execute([':d' => $diferencia, ':p' => $linea['id_producto']]);

            $this->recalcularTotal((int) $linea['id_pedido']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Elimina la línea, devuelve las unidades al inventario y recalcula el total. */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $linea = $this->bloquearLinea($id);
            $this->bloquearProducto((int) $linea['id_producto']);

            $this->db->prepare("UPDATE productos SET stock = stock + :c WHERE id_producto = :p")
                ->execute([':c' => $linea['cantidad'], ':p' => $linea['id_producto']]);

            $this->db->prepare("DELETE FROM detalle_pedido WHERE id_detalle = :id")
                ->execute([':id' => $id]);

            $this->recalcularTotal((int) $linea['id_pedido']);
            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Estado del pedido al que pertenece la línea (para impedir cambios en pedidos anulados). */
    public function estadoPedidoDeLinea(int $id): string|false
    {
        $stmt = $this->db->prepare(
            "SELECT p.estado FROM pedidos p INNER JOIN detalle_pedido d ON d.id_pedido = p.id_pedido
             WHERE d.id_detalle = :id"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn();
    }

    private function bloquearLinea(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM detalle_pedido WHERE id_detalle = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $linea = $stmt->fetch();
        if (!$linea) {
            throw new DomainException('El detalle indicado no existe.');
        }

        return $linea;
    }

    private function bloquearProducto(int $id): array
    {
        $stmt = $this->db->prepare("SELECT id_producto, stock FROM productos WHERE id_producto = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    private function recalcularTotal(int $idPedido): void
    {
        $this->db->prepare(
            "UPDATE pedidos
             SET total = COALESCE((SELECT SUM(subtotal) FROM detalle_pedido WHERE id_pedido = :a), 0)
             WHERE id_pedido = :b"
        )->execute([':a' => $idPedido, ':b' => $idPedido]);
    }
}
