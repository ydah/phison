<?php

declare(strict_types=1);

namespace Phison\Lalr;

final class LrItem
{
    public function __construct(
        public readonly int $productionId,
        public readonly int $position,
        public readonly int $lookaheadTerminalId,
    ) {
    }

    public function key(): string
    {
        return $this->productionId . ':' . $this->position . ':' . $this->lookaheadTerminalId;
    }

    public function coreKey(): string
    {
        return $this->productionId . ':' . $this->position;
    }
}
