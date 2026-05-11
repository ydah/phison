<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class ActionCode
{
    public function __construct(
        public readonly string $code,
    ) {}
}
