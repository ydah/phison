<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class PackedActionTable
{
    /**
     * @param array<int, int> $defaultActionByState
     */
    public function __construct(
        public readonly PackedTable $explicit,
        public readonly array $defaultActionByState,
        public readonly PackedTable $defaultTokens,
    ) {
    }
}
