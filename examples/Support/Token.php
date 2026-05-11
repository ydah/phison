<?php

declare(strict_types=1);

namespace Example\Support;

use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;

final class Token implements TokenInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly mixed $value,
        private readonly SourceRange $location,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function location(): SourceRange
    {
        return $this->location;
    }
}
