<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class Precedence
{
    public function __construct(
        public readonly string $symbol,
        public readonly int $level,
        public readonly string $associativity,
    ) {
    }
}
