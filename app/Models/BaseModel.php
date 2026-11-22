<?php

namespace App\Models;

use PDO;


abstract class BaseModel
{
    protected string $table;
    protected string $pk;
    /** Columnas que la API puede insertar/actualizar */
    protected array $columns = [];
    /** Columnas por las que se puede filtrar con ?columna=valor */
    protected array $filterable = [];
    /** Columnas donde busca el parámetro ?q=texto (LIKE) */
    protected array $searchable = [];
    protected string $orderBy = '';

    public function __construct(protected PDO $db)
    {
    }

    public function columns(): array
    {
        return $this->columns;
    }

    /** Consulta base (los hijos pueden agregar JOINs o excluir columnas sensibles). */
    protected function selectSql(): string
    {
        return "SELECT t.* FROM {$this->table} t";
    }

    public function all(array $query = []): array
    {
        $where = [];
        $params = [];

        foreach ($this->filterable as $col) {
            if (isset($query[$col]) && $query[$col] !== '') {
                $where[] = "t.{$col} = :f_{$col}";
                $params[":f_{$col}"] = $query[$col];
            }
        }

        if (!empty($query['q']) && $this->searchable) {
            $likes = [];
            foreach ($this->searchable as $i => $col) {
                $likes[] = "t.{$col} LIKE :q{$i}";
                $params[":q{$i}"] = '%' . $query['q'] . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }

        $sql = $this->selectSql();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ' . ($this->orderBy ?: "t.{$this->pk}");

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): array|false
    {
        $stmt = $this->db->prepare($this->selectSql() . " WHERE t.{$this->pk} = :id");
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function create(array $data): int
    {
        $data = array_intersect_key($data, array_flip($this->columns));

        $cols = array_keys($data);
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $cols) . ') VALUES ('
             . implode(', ', array_map(fn($c) => ":{$c}", $cols)) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_combine(array_map(fn($c) => ":{$c}", $cols), array_values($data)));

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = array_intersect_key($data, array_flip($this->columns));
        if (!$data) {
            return false;
        }

        $set = implode(', ', array_map(fn($c) => "{$c} = :{$c}", array_keys($data)));
        $stmt = $this->db->prepare("UPDATE {$this->table} SET {$set} WHERE {$this->pk} = :_id");

        $params = [':_id' => $id];
        foreach ($data as $c => $v) {
            $params[":{$c}"] = $v;
        }

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->pk} = :id");

        return $stmt->execute([':id' => $id]);
    }
}
