<?php

declare(strict_types=1);

namespace MoloniOn\Models;

/**
 * Key-value settings store (mod_moloni_on_config).
 */
class Config extends AbstractModel
{
    public static function table(): string
    {
        return 'mod_moloni_on_config';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = self::query()->where('setting_key', $key)->first();

        return $row->setting_value ?? $default;
    }

    /**
     * @return array<string,string>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::query()->get() as $row) {
            $out[$row->setting_key] = $row->setting_value;
        }

        return $out;
    }

    public static function set(string $key, ?string $value): void
    {
        self::query()->updateOrInsert(
            ['setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]
        );
    }
}
