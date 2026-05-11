<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class Terminal extends Symbol
{
    public function __construct(
        int $id,
        string $name,
        public readonly ?string $displayName = null,
    ) {
        parent::__construct($id, $name);
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
