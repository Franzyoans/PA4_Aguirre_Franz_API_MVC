<?php

namespace Config;

/**
 * Lector mínimo de archivos .env (clave=valor).
 */
class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            self::$vars[trim($clave)] = trim($valor, " \t\"'");
        }
    }

    public static function get(string $clave, ?string $defecto = null): ?string
    {
        return self::$vars[$clave] ?? $defecto;
    }
}
