<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class NonTerminal extends Symbol
{
    public function isTerminal(): bool
    {
        return false;
    }
}
