<?php

declare(strict_types=1);

namespace Phison\Lalr;

final class Conflict
{
    public const SHIFT_REDUCE = 'shift/reduce';
    public const REDUCE_REDUCE = 'reduce/reduce';
    public const OTHER = 'other';

    public function __construct(
        public readonly int $stateId,
        public readonly int $tokenId,
        public readonly string $kind,
        public readonly int $existingAction,
        public readonly int $incomingAction,
        public readonly bool $resolved,
        public readonly ?string $resolution,
    ) {}
}
