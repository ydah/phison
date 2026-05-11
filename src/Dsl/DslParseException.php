<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class DslParseException extends \RuntimeException
{
    public static function at(string $message, string $source, int $offset, ?string $file = null): self
    {
        [$line, $column] = self::lineColumn($source, $offset);
        $place = ($file ?? '<grammar>') . ':' . $line . ':' . $column;

        return new self($place . ': ' . $message);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private static function lineColumn(string $source, int $offset): array
    {
        $prefix = substr($source, 0, max(0, $offset));
        $line = substr_count($prefix, "\n") + 1;
        $lastNewline = strrpos($prefix, "\n");
        $column = $lastNewline === false
            ? strlen($prefix) + 1
            : strlen($prefix) - $lastNewline;

        return [$line, $column];
    }
}
