<?php

declare(strict_types=1);

namespace Phison\Runtime;

interface TokenInterface
{
    public function id(): int;

    public function name(): string;

    public function value(): mixed;

    public function location(): SourceRange;
}
