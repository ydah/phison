<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class SymbolReference
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
    ) {
    }
}
