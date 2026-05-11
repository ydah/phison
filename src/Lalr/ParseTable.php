<?php

declare(strict_types=1);

namespace Phison\Lalr;

final class ParseTable
{
    /**
     * Encoded ACTION values:
     *   > 0: shift to value - 1
     *   < 0: reduce by -value - 1
     *   = 0: accept
     *
     * @param array<int, array<int, int>> $actions
     * @param array<int, array<int, int>> $gotos
     * @param array<int, list<int>> $expected
     * @param list<Conflict> $conflicts
     */
    public function __construct(
        public readonly array $actions,
        public readonly array $gotos,
        public readonly array $expected,
        public readonly array $conflicts,
    ) {}

    public function unresolvedConflictCount(): int
    {
        $count = 0;
        foreach ($this->conflicts as $conflict) {
            if (!$conflict->resolved) {
                $count++;
            }
        }

        return $count;
    }

    public function actionCount(): int
    {
        $count = 0;
        foreach ($this->actions as $row) {
            $count += count($row);
        }

        return $count;
    }

    public function gotoCount(): int
    {
        $count = 0;
        foreach ($this->gotos as $row) {
            $count += count($row);
        }

        return $count;
    }
}
