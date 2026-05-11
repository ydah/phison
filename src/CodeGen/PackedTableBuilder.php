<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class PackedTableBuilder
{
    /**
     * @param array<int, array<int, int>> $actions
     */
    public function packActions(array $actions): PackedActionTable
    {
        [$defaults, $defaultTokenRows, $explicitRows] = $this->splitDefaultReductions($actions);

        return new PackedActionTable(
            $this->packRows($explicitRows),
            $defaults,
            $this->packRows($defaultTokenRows),
        );
    }

    /**
     * @param array<int, array<int, int>> $rows
     */
    public function packRows(array $rows): PackedTable
    {
        [$rowForState, $uniqueRows] = $this->deduplicateRows($rows);
        $base = [];
        $check = [];
        $value = [];

        foreach ($this->packingOrder($uniqueRows) as $rowId) {
            $row = $uniqueRows[$rowId];
            $base[$rowId] = $this->findBase($row, $check);
            foreach ($row as $symbol => $entry) {
                $index = $base[$rowId] + $symbol;
                $check[$index] = $rowId;
                $value[$index] = $entry;
            }
        }

        ksort($rowForState, SORT_NUMERIC);
        ksort($base, SORT_NUMERIC);
        ksort($check, SORT_NUMERIC);
        ksort($value, SORT_NUMERIC);

        return new PackedTable($rowForState, $base, $check, $value);
    }

    /**
     * @param array<int, array<int, int>> $actions
     * @return array{array<int, int>, array<int, array<int, int>>, array<int, array<int, int>>}
     */
    private function splitDefaultReductions(array $actions): array
    {
        $defaults = [];
        $defaultTokenRows = [];
        $explicitRows = [];

        foreach ($actions as $state => $row) {
            $default = $this->dominantReduction($row);
            if ($default === null) {
                $explicitRows[$state] = $row;
                continue;
            }

            foreach ($row as $token => $action) {
                if ($action === $default) {
                    $defaultTokenRows[$state][$token] = 1;
                    continue;
                }

                $explicitRows[$state][$token] = $action;
            }

            $defaults[$state] = $default;
        }

        ksort($defaults, SORT_NUMERIC);

        return [$defaults, $defaultTokenRows, $explicitRows];
    }

    /**
     * @param array<int, int> $row
     */
    private function dominantReduction(array $row): ?int
    {
        $counts = [];
        foreach ($row as $action) {
            if ($action >= 0) {
                continue;
            }

            $counts[$action] = ($counts[$action] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts, SORT_NUMERIC);
        $action = (int) array_key_first($counts);

        return $counts[$action] >= 2 ? $action : null;
    }

    /**
     * @param array<int, array<int, int>> $rows
     * @return array{array<int, int>, array<int, array<int, int>>}
     */
    private function deduplicateRows(array $rows): array
    {
        $rowForState = [];
        $signatures = [];
        $uniqueRows = [];

        foreach ($rows as $state => $row) {
            if ($row === []) {
                continue;
            }

            ksort($row, SORT_NUMERIC);
            $signature = serialize($row);
            if (!isset($signatures[$signature])) {
                $signatures[$signature] = count($uniqueRows);
                $uniqueRows[] = $row;
            }

            $rowForState[$state] = $signatures[$signature];
        }

        return [$rowForState, $uniqueRows];
    }

    /**
     * @param array<int, array<int, int>> $rows
     * @return list<int>
     */
    private function packingOrder(array $rows): array
    {
        $rowIds = array_keys($rows);
        usort($rowIds, function (int $left, int $right) use ($rows): int {
            $byEntryCount = count($rows[$right]) <=> count($rows[$left]);
            if ($byEntryCount !== 0) {
                return $byEntryCount;
            }

            $bySpan = $this->rowSpan($rows[$right]) <=> $this->rowSpan($rows[$left]);
            if ($bySpan !== 0) {
                return $bySpan;
            }

            return $left <=> $right;
        });

        return $rowIds;
    }

    /**
     * @param array<int, int> $row
     * @param array<int, int> $check
     */
    private function findBase(array $row, array $check): int
    {
        if ($row === []) {
            return 0;
        }

        $symbols = array_keys($row);
        $start = -((int) min($symbols));
        for ($base = $start; ; $base++) {
            foreach ($symbols as $symbol) {
                if (isset($check[$base + $symbol])) {
                    continue 2;
                }
            }

            return $base;
        }
    }

    /**
     * @param array<int, int> $row
     */
    private function rowSpan(array $row): int
    {
        if ($row === []) {
            return 0;
        }

        $symbols = array_keys($row);

        return (int) max($symbols) - (int) min($symbols) + 1;
    }
}
