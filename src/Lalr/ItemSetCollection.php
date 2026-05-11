<?php

declare(strict_types=1);

namespace Phison\Lalr;

final class ItemSetCollection
{
    /**
     * @param list<LrItemSet> $states
     */
    public function __construct(
        public readonly array $states,
        public readonly int $canonicalStateCount,
    ) {}

    public function state(int $id): LrItemSet
    {
        return $this->states[$id]
            ?? throw new \OutOfBoundsException('Unknown state: ' . (string) $id);
    }
}
