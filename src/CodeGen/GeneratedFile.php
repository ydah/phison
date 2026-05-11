<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class GeneratedFile
{
    public function __construct(
        public readonly string $contents,
    ) {
    }
}
