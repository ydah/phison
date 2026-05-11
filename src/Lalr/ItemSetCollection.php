<?php

declare(strict_types=1);

namespace Phison\Lalr;

final class ItemSetCollection
{
    /**
     * @param list<LrItemSet> $states
     * @param array<int, list<int>> $canonicalStateIdsByState
     */
    public function __construct(
        public readonly array $states,
        public readonly int $canonicalStateCount,
        public readonly array $canonicalStateIdsByState = [],
    ) {
    }

    public function state(int $id): LrItemSet
    {
        return $this->states[$id]
            ?? throw new \OutOfBoundsException('Unknown state: ' . (string) $id);
    }

    /**
     * @return list<int>
     */
    public function canonicalStateIdsFor(int $stateId): array
    {
        return $this->canonicalStateIdsByState[$stateId] ?? [$stateId];
    }
}
