<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class CodegenOptions
{
    public function __construct(
        public readonly ?string $namespace = null,
        public readonly ?string $className = null,
        public readonly string $targetPhp = '8.2',
        public readonly string $tableLayout = 'array',
    ) {
    }
}
