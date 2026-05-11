<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class SymbolRef
{
    public function __construct(
        public readonly Symbol $symbol,
        public readonly ?string $label = null,
    ) {}
}
