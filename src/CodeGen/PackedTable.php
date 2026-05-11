<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class PackedTable
{
    /**
     * @param array<int, int> $rowForState
     * @param array<int, int> $base
     * @param array<int, int> $check
     * @param array<int, int> $value
     */
    public function __construct(
        public readonly array $rowForState,
        public readonly array $base,
        public readonly array $check,
        public readonly array $value,
    ) {
    }
}
