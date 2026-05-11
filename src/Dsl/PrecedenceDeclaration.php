<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class PrecedenceDeclaration
{
    public const LEFT = 'left';
    public const RIGHT = 'right';
    public const NONASSOC = 'nonassoc';
    public const PRECEDENCE = 'precedence';

    /**
     * @param non-empty-list<string> $symbols
     */
    public function __construct(
        public readonly string $associativity,
        public readonly array $symbols,
    ) {
    }
}
