<?php

declare(strict_types=1);

namespace Phison\Report;

use Phison\Grammar\Grammar;
use Phison\Grammar\NonTerminal;
use Phison\Grammar\SymbolRef;
use Phison\Grammar\Terminal;
use Phison\Lalr\ItemSetCollection;

final class WitnessGenerator
{
    /**
     * @return list<string>
     */
    public function tokenSequenceForState(Grammar $grammar, ItemSetCollection $collection, int $stateId, int $lookaheadTokenId): array
    {
        $symbols = $this->symbolPathToState($collection, $stateId);
        $derivations = $this->shortestDerivations($grammar);
        $tokens = [];

        foreach ($symbols as $symbolKey) {
            [$kind, $id] = explode(':', $symbolKey, 2);
            if ($kind === 't') {
                $tokens[] = $grammar->terminalById((int) $id)->name;
                continue;
            }

            foreach ($derivations[(int) $id] ?? ['<' . ($grammar->nonTerminalsById[(int) $id]->name ?? $id) . '>'] as $token) {
                $tokens[] = $token;
            }
        }

        $tokens[] = $grammar->terminalById($lookaheadTokenId)->name;

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function symbolPathToState(ItemSetCollection $collection, int $targetStateId): array
    {
        if ($targetStateId === 0) {
            return [];
        }

        $queue = [[0, []]];
        $visited = [0 => true];
        while ($queue !== []) {
            [$stateId, $path] = array_shift($queue);
            foreach ($collection->state($stateId)->transitions as $symbolKey => $nextStateId) {
                if (isset($visited[$nextStateId])) {
                    continue;
                }

                $nextPath = [...$path, $symbolKey];
                if ($nextStateId === $targetStateId) {
                    return $nextPath;
                }

                $visited[$nextStateId] = true;
                $queue[] = [$nextStateId, $nextPath];
            }
        }

        return [];
    }

    /**
     * @return array<int, list<string>>
     */
    private function shortestDerivations(Grammar $grammar): array
    {
        $derivations = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->productions as $production) {
                if ($production->lhs->name === Grammar::ACCEPT) {
                    continue;
                }

                $candidate = $this->productionDerivation($production->rhs, $derivations);
                if ($candidate === null) {
                    continue;
                }

                $current = $derivations[$production->lhs->id] ?? null;
                if ($current === null || count($candidate) < count($current)) {
                    $derivations[$production->lhs->id] = $candidate;
                    $changed = true;
                }
            }
        }

        return $derivations;
    }

    /**
     * @param list<SymbolRef> $rhs
     * @param array<int, list<string>> $derivations
     * @return list<string>|null
     */
    private function productionDerivation(array $rhs, array $derivations): ?array
    {
        $tokens = [];
        foreach ($rhs as $reference) {
            $symbol = $reference->symbol;
            if ($symbol instanceof Terminal) {
                $tokens[] = $symbol->name;
                continue;
            }

            if (!$symbol instanceof NonTerminal || !isset($derivations[$symbol->id])) {
                return null;
            }

            array_push($tokens, ...$derivations[$symbol->id]);
        }

        return $tokens;
    }
}
