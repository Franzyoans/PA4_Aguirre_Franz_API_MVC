<?php

namespace Core;

use PDO;

/**
 * Validador de datos de entrada.
 * Reglas: required, string, int, numeric, email, datetime,
 *         min:n, max:n, in:a,b,c, digits:n, digits_between:a,b, exists:tabla,columna
 */
class Validator
{
    /**
     * @param bool $parcial Si es true (PATCH) solo se validan los campos enviados.
     * @return array Errores por campo (vacío si todo es válido).
     */
    public static function validar(array $datos, array $reglas, PDO $db, bool $parcial = false): array
    {
        $errores = [];

        foreach ($reglas as $campo => $regla) {
            $lista = explode('|', $regla);
            $requerido = in_array('required', $lista, true);
            $enviado = array_key_exists($campo, $datos);
            $valor = $datos[$campo] ?? null;
            $vacio = $valor === null || (is_string($valor) && trim($valor) === '');

            if (!$enviado || $vacio) {
                if ($requerido && (!$parcial || $enviado)) {
                    $errores[$campo] = "El campo {$campo} es obligatorio.";
                }
                continue;
            }

            $esNumerico = in_array('int', $lista, true) || in_array('numeric', $lista, true);

            foreach ($lista as $r) {
                [$nombre, $param] = array_pad(explode(':', $r, 2), 2, null);
                $error = self::aplicar($nombre, $param, $campo, $valor, $esNumerico, $db);
                if ($error !== null) {
                    $errores[$campo] = $error;
                    break;
                }
            }
        }

        return $errores;
    }

    /** Largo en caracteres (funciona aunque la extensión mbstring no esté activa). */
    private static function largo(string $texto): int
    {
        return function_exists('mb_strlen') ? mb_strlen($texto) : (int) preg_match_all('/./us', $texto);
    }

    private static function aplicar(string $regla, ?string $param, string $campo, mixed $valor, bool $esNumerico, PDO $db): ?string
    {
        switch ($regla) {
            case 'string':
                return is_string($valor) ? null : "El campo {$campo} debe ser texto.";

            case 'int':
                return (!is_bool($valor) && filter_var($valor, FILTER_VALIDATE_INT) !== false)
                    ? null : "El campo {$campo} debe ser un número entero.";

            case 'numeric':
                return (!is_bool($valor) && is_numeric($valor))
                    ? null : "El campo {$campo} debe ser numérico.";

            case 'email':
                return filter_var($valor, FILTER_VALIDATE_EMAIL)
                    ? null : "El campo {$campo} debe ser un correo electrónico válido.";

            case 'datetime':
                $f = \DateTime::createFromFormat('Y-m-d H:i:s', (string) $valor);
                return ($f && $f->format('Y-m-d H:i:s') === $valor)
                    ? null : "El campo {$campo} debe tener el formato YYYY-MM-DD HH:MM:SS.";

            case 'min':
                if ($esNumerico) {
                    return ($valor + 0) >= (float) $param ? null : "El campo {$campo} debe ser como mínimo {$param}.";
                }
                return self::largo((string) $valor) >= (int) $param
                    ? null : "El campo {$campo} debe tener al menos {$param} caracteres.";

            case 'max':
                if ($esNumerico) {
                    return ($valor + 0) <= (float) $param ? null : "El campo {$campo} debe ser como máximo {$param}.";
                }
                return self::largo((string) $valor) <= (int) $param
                    ? null : "El campo {$campo} no debe superar los {$param} caracteres.";

            case 'in':
                $permitidos = explode(',', (string) $param);
                return in_array((string) $valor, $permitidos, true)
                    ? null : "El campo {$campo} debe ser uno de: " . implode(', ', $permitidos) . '.';

            case 'digits':
                return (ctype_digit((string) $valor) && strlen((string) $valor) === (int) $param)
                    ? null : "El campo {$campo} debe tener exactamente {$param} dígitos.";

            case 'digits_between':
                [$a, $b] = array_map('intval', explode(',', (string) $param));
                $len = strlen((string) $valor);
                return (ctype_digit((string) $valor) && $len >= $a && $len <= $b)
                    ? null : "El campo {$campo} debe tener entre {$a} y {$b} dígitos.";

            case 'exists':
                [$tabla, $col] = explode(',', (string) $param);
                // $tabla y $col provienen del código (no del usuario)
                $stmt = $db->prepare("SELECT 1 FROM {$tabla} WHERE {$col} = :v LIMIT 1");
                $stmt->execute([':v' => $valor]);
                return $stmt->fetchColumn() ? null : "El valor indicado en {$campo} no existe en {$tabla}.";
        }

        return null; // required u otras reglas sin chequeo adicional
    }
}
