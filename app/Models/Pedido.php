<?php

namespace App\Models;

use Throwable;

class Pedido extends BaseModel
{
    protected string $table = 'pedidos';
    protected string $pk = 'id_pedido';
    // "total" no es editable: lo calcula el detalle del pedido
    protected array $columns = ['id_cliente', 'id_usuario', 'fecha', 'estado'];
    protected array $filterable = ['id_cliente', 'id_usuario', 'estado'];
    protected string $orderBy = 't.fecha DESC';

    protected function selectSql(): string
    {
        return "SELECT t.id_pedido, t.id_cliente, c.nombre AS cliente,
                       t.id_usuario, u.nombre AS vendedor,
                       t.fecha, t.estado, t.total
                FROM pedidos t
                INNER JOIN clientes c ON c.id_cliente = t.id_cliente
                INNER JOIN usuarios u ON u.id_usuario = t.id_usuario";
    }

    /** Líneas del pedido con el nombre del producto. */
    public function detalles(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT d.id_detalle, d.id_producto, p.codigo, p.nombre AS producto,
                    d.cantidad, d.precio_unitario, d.subtotal
             FROM detalle_pedido d
             INNER JOIN productos p ON p.id_producto = d.id_producto
             WHERE d.id_pedido = :id
             ORDER BY d.id_detalle"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll();
    }

    /** Devuelve al inventario las unidades de todas las líneas del pedido. */
    private function devolverStock(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE productos p
             INNER JOIN detalle_pedido d ON d.id_producto = p.id_producto
             SET p.stock = p.stock + d.cantidad
             WHERE d.id_pedido = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    /** Estado actual del pedido (para validar transiciones). */
    public function estado(int $id): string|false
    {
        $stmt = $this->db->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn();
    }

    /** Si el pedido pasa a ANULADO, el stock vuelve al inventario. */
    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $actual = $this->estado($id);
            if (($data['estado'] ?? null) === 'ANULADO' && $actual !== 'ANULADO') {
                $this->devolverStock($id);
            }
            $ok = parent::update($id, $data);
            $this->db->commit();

            return $ok;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Al eliminar un pedido no anulado se devuelve el stock; el detalle se borra en cascada. */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            if ($this->estado($id) !== 'ANULADO') {
                $this->devolverStock($id);
            }
            $ok = parent::delete($id);
            $this->db->commit();

            return $ok;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
