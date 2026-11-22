<?php

namespace App\Controllers;

use App\Models\BaseModel;
use Core\Request;
use Core\Response;
use Core\Validator;
use PDO;
use PDOException;

/**
 * Controlador base: procesa la solicitud, valida y responde.
 * Los controladores hijos definen las reglas de validación de su recurso.
 *
 * Códigos HTTP usados: 200 OK, 201 Creado, 400 Datos inválidos,
 * 404 No encontrado, 409 Conflicto (duplicado / relaciones / stock), 500 Error interno.
 */
abstract class BaseController
{
    protected BaseModel $model;
    /** Nombre en singular y plural, usados en los mensajes */
    protected string $entidad = 'registro';
    protected string $plural = 'registros';

    public function __construct(protected PDO $db)
    {
    }

    /** Reglas de validación. $creando = true en POST. */
    abstract protected function reglas(bool $creando): array;

    /** Validaciones adicionales (reglas entre campos). Devuelve errores por campo. */
    protected function validacionExtra(array $datos, bool $creando, ?array $actual): array
    {
        return [];
    }

    /** Ajustes antes de guardar (por ejemplo, cifrar una clave). */
    protected function preparar(array $datos): array
    {
        return $datos;
    }

    // ------------------------------------------------------------------ LISTAR
    public function index(): void
    {
        Response::success(
            $this->model->all($_GET),
            "Listado de {$this->plural} obtenido correctamente"
        );
    }

    // --------------------------------------------------------------- POR ID
    public function show(int $id): void
    {
        Response::success($this->obtenerOFallar($id), 'Consulta de ' . $this->nombreEntidad() . ' realizada correctamente');
    }

    // ------------------------------------------------------------------ CREAR
    public function store(): void
    {
        $datos = Request::body();
        $this->validarOFallar($datos, true, null);

        $id = $this->ejecutar(fn() => $this->model->create($this->preparar($datos)));

        Response::success($this->model->find($id), 'Registro creado correctamente', 201);
    }

    // ------------------------------------------------------ ACTUALIZAR (PUT)
    public function update(int $id): void
    {
        $this->actualizar($id, false);
    }

    // ---------------------------------------------------- ACTUALIZAR (PATCH)
    public function patch(int $id): void
    {
        $this->actualizar($id, true);
    }

    protected function actualizar(int $id, bool $parcial): void
    {
        $actual = $this->obtenerOFallar($id);
        $datos = Request::body();

        $editables = array_intersect_key($datos, array_flip($this->model->columns() + ['clave']));
        if (!$editables) {
            Response::error('No se enviaron campos válidos para actualizar.', 400);
        }

        $this->validarOFallar($datos, false, $actual, $parcial);
        $this->ejecutar(fn() => $this->model->update($id, $this->preparar($datos)));

        Response::success($this->model->find($id), 'Registro actualizado correctamente');
    }

    // ------------------------------------------------------------- ELIMINAR
    public function destroy(int $id): void
    {
        $this->obtenerOFallar($id);
        $this->ejecutar(fn() => $this->model->delete($id));

        Response::success(null, 'Registro eliminado correctamente');
    }

    // ============================================================ AUXILIARES

    /** Nombre de la entidad sin el artículo ("el producto" -> "producto"). */
    protected function nombreEntidad(): string
    {
        return preg_replace('/^(el|la|los|las) /u', '', $this->entidad);
    }

    protected function obtenerOFallar(int $id): array
    {
        $fila = $this->model->find($id);
        if (!$fila) {
            Response::error("No se encontró {$this->entidad} con id {$id}.", 404);
        }

        return $fila;
    }

    protected function validarOFallar(array $datos, bool $creando, ?array $actual, bool $parcial = false): void
    {
        $errores = Validator::validar($datos, $this->reglas($creando), $this->db, $parcial);
        $errores += $this->validacionExtra($datos, $creando, $actual);

        if ($errores) {
            Response::error('Los datos enviados no son válidos.', 400, $errores);
        }
    }

    /** Ejecuta una operación de BD y traduce los errores de MySQL a respuestas claras. */
    protected function ejecutar(callable $accion): mixed
    {
        try {
            return $accion();
        } catch (PDOException $e) {
            $this->traducirErrorBD($e);
        } catch (\DomainException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    protected function traducirErrorBD(PDOException $e): never
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);

        switch ($codigo) {
            case 1062:
                Response::error('Ya existe un registro con ese valor único (código, RUC, documento o correo).', 409);
            case 1451:
                Response::error('No se puede eliminar: el registro tiene datos relacionados en otras tablas.', 409);
            case 1452:
                Response::error('La clave foránea indicada no existe.', 400);
            case 4025:
            case 3819:
                Response::error('Un valor no cumple las restricciones definidas en la base de datos.', 400);
            case 1644: // SIGNAL del procedimiento almacenado (mensaje propio, seguro de mostrar)
                Response::error($e->errorInfo[2] ?? 'La operación no es válida.', 409);
        }

        throw $e; // cualquier otro error -> 500 genérico (se registra en el log)
    }
}
