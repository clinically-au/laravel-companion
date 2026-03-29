<?php

declare(strict_types=1);

namespace Clinically\Companion\Support;

use Illuminate\Database\Eloquent\Model;

final class CompanionConfig
{
    /**
     * @return class-string<Model>
     */
    public static function model(string $key): string
    {
        /** @var class-string<Model> */
        return config("companion.models_map.{$key}");
    }

    public static function table(string $key): string
    {
        /** @var string */
        return config("companion.tables.{$key}");
    }

    public static function newModel(string $key): Model
    {
        $class = self::model($key);

        return new $class;
    }
}
