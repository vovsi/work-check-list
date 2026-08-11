<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Читает config/params.ini (не в git — см. config/params.ini.example) */
final class Config
{
    private static ?array $data = null;

    public static function llm(): array
    {
        $data = self::load();

        if (!isset($data['llm']['host'], $data['llm']['model'])) {
            throw new RuntimeException(
                'В config/params.ini не заданы llm.host и llm.model — скопируйте config/params.ini.example'
            );
        }

        return $data['llm'];
    }

    private static function load(): array
    {
        if (self::$data === null) {
            $path = dirname(__DIR__) . '/config/params.ini';
            if (!is_file($path)) {
                throw new RuntimeException(
                    'Не найден config/params.ini — скопируйте config/params.ini.example и заполните его'
                );
            }

            $parsed = parse_ini_file($path, true);
            self::$data = $parsed === false ? [] : $parsed;
        }

        return self::$data;
    }
}
