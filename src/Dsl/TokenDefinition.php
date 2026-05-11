<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class TokenDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $display = null,
    ) {}
}
