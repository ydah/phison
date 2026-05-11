<?php

declare(strict_types=1);

namespace Phison\Grammar;

abstract class Symbol
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}

    abstract public function isTerminal(): bool;
}
