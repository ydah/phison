<?php

declare(strict_types=1);

namespace Phison\Lalr;

use Phison\Analysis\FirstSet;
use Phison\Analysis\NullableSet;
use Phison\Grammar\Grammar;
use Phison\Grammar\NonTerminal;
use Phison\Grammar\Production;
use Phison\Grammar\Symbol;

final class CanonicalLr1ThenMergeBuilder
{
    private Grammar $grammar;
    private FirstSet $firstSet;

    public function build(Grammar $grammar): ItemSetCollection
    {
        $this->grammar = $grammar;
        $nullable = new NullableSet($grammar);
        $this->firstSet = new FirstSet($grammar, $nullable);

        $canonicalStates = $this->buildCanonicalStates($grammar);

        return $this->mergeByCore($canonicalStates);
    }

    /**
     * @return list<LrItemSet>
     */
    private function buildCanonicalStates(Grammar $grammar): array
    {
        $initialItems = $this->closure([
            (new LrItem(0, 0, $grammar->eof->id))->key() => new LrItem(0, 0, $grammar->eof->id),
        ]);

        $initial = $this->makeState(0, $initialItems);
        $states = [$initial];
        $stateIdsByIdentity = [$initial->identityKey() => 0];
        $queue = [0];

        while ($queue !== []) {
            $stateId = array_shift($queue);
            $state = $states[$stateId];
            $symbols = $this->nextSymbols($state);
            $transitions = [];

            foreach ($symbols as $symbolKey => $symbol) {
                $gotoItems = $this->goto($state, $symbol);
                if ($gotoItems === []) {
                    continue;
                }

                $candidate = $this->makeState(count($states), $gotoItems);
                $identity = $candidate->identityKey();
                if (!isset($stateIdsByIdentity[$identity])) {
                    $stateIdsByIdentity[$identity] = count($states);
                    $states[] = $candidate;
                    $queue[] = $candidate->id;
                }

                $transitions[$symbolKey] = $stateIdsByIdentity[$identity];
            }

            $states[$stateId] = new LrItemSet($state->id, $state->items, $transitions);
        }

        return $states;
    }

    /**
     * @param array<string, LrItem> $items
     * @return array<string, LrItem>
     */
    private function closure(array $items): array
    {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($items as $item) {
                $production = $this->grammar->production($item->productionId);
                $symbol = $this->symbolAfterDot($production, $item->position);
                if (!$symbol instanceof NonTerminal) {
                    continue;
                }

                $lookaheads = $this->firstSet->forSequenceWithLookahead(
                    $production->rhs,
                    $item->position + 1,
                    $item->lookaheadTerminalId,
                );

                foreach ($this->grammar->productionsFor($symbol) as $nextProduction) {
                    foreach ($lookaheads as $lookahead) {
                        $nextItem = new LrItem($nextProduction->id, 0, $lookahead);
                        if (!isset($items[$nextItem->key()])) {
                            $items[$nextItem->key()] = $nextItem;
                            $changed = true;
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * @return array<string, Symbol>
     */
    private function nextSymbols(LrItemSet $state): array
    {
        $symbols = [];
        foreach ($state->items as $item) {
            $production = $this->grammar->production($item->productionId);
            $symbol = $this->symbolAfterDot($production, $item->position);
            if ($symbol !== null) {
                $symbols[LrItemSet::symbolKey($symbol)] = $symbol;
            }
        }

        ksort($symbols, SORT_STRING);

        return $symbols;
    }

    /**
     * @return array<string, LrItem>
     */
    private function goto(LrItemSet $state, Symbol $symbol): array
    {
        $items = [];
        $targetKey = LrItemSet::symbolKey($symbol);
        foreach ($state->items as $item) {
            $production = $this->grammar->production($item->productionId);
            $nextSymbol = $this->symbolAfterDot($production, $item->position);
            if ($nextSymbol !== null && LrItemSet::symbolKey($nextSymbol) === $targetKey) {
                $nextItem = new LrItem($item->productionId, $item->position + 1, $item->lookaheadTerminalId);
                $items[$nextItem->key()] = $nextItem;
            }
        }

        return $this->closure($items);
    }

    private function symbolAfterDot(Production $production, int $position): ?Symbol
    {
        return $production->rhs[$position]->symbol ?? null;
    }

    /**
     * @param array<string, LrItem> $items
     */
    private function makeState(int $id, array $items): LrItemSet
    {
        ksort($items, SORT_STRING);

        return new LrItemSet($id, array_values($items));
    }

    /**
     * @param list<LrItemSet> $canonicalStates
     */
    private function mergeByCore(array $canonicalStates): ItemSetCollection
    {
        $groups = [];
        foreach ($canonicalStates as $state) {
            $groups[$state->coreKey()][] = $state->id;
        }

        uasort($groups, static fn (array $a, array $b): int => min($a) <=> min($b));

        $mergedIdByCanonicalId = [];
        $itemsByMergedId = [];
        $mergedId = 0;
        foreach ($groups as $canonicalIds) {
            $items = [];
            foreach ($canonicalIds as $canonicalId) {
                $mergedIdByCanonicalId[$canonicalId] = $mergedId;
                foreach ($canonicalStates[$canonicalId]->items as $item) {
                    $items[$item->key()] = $item;
                }
            }

            ksort($items, SORT_STRING);
            $itemsByMergedId[$mergedId] = array_values($items);
            $mergedId++;
        }

        $transitionsByMergedId = array_fill(0, $mergedId, []);
        foreach ($canonicalStates as $state) {
            $from = $mergedIdByCanonicalId[$state->id];
            foreach ($state->transitions as $symbolKey => $targetCanonicalId) {
                $target = $mergedIdByCanonicalId[$targetCanonicalId];
                if (isset($transitionsByMergedId[$from][$symbolKey]) && $transitionsByMergedId[$from][$symbolKey] !== $target) {
                    throw new \LogicException('Inconsistent LALR transition while merging states.');
                }

                $transitionsByMergedId[$from][$symbolKey] = $target;
            }
        }

        $states = [];
        foreach ($itemsByMergedId as $id => $items) {
            ksort($transitionsByMergedId[$id], SORT_STRING);
            $states[] = new LrItemSet($id, $items, $transitionsByMergedId[$id]);
        }

        return new ItemSetCollection($states, count($canonicalStates));
    }
}
