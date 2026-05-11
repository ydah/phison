<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class TableLayout
{
    public const ARRAY = 'array';
    public const SWITCH = 'switch';
    public const PACKED = 'packed';
    public const HYBRID = 'hybrid';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ARRAY,
            self::SWITCH,
            self::PACKED,
            self::HYBRID,
        ];
    }
}
