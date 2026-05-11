<?php

declare(strict_types=1);

namespace Phison\Analysis;

use Phison\Grammar\Grammar;
use Phison\Grammar\NonTerminal;
use Phison\Grammar\SymbolRef;
use Phison\Grammar\Terminal;

final class FirstSet
{
    /** @var array<int, array<int, true>> */
    private array $firstByNonTerminal = [];

    public function __construct(
        Grammar $grammar,
        private readonly NullableSet $nullable,
    ) {
        foreach ($grammar->nonTerminalsById as $nonTerminal) {
            $this->firstByNonTerminal[$nonTerminal->id] = [];
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->productions as $production) {
                foreach ($production->rhs as $reference) {
                    $symbol = $reference->symbol;
                    if ($symbol instanceof Terminal) {
                        if (!isset($this->firstByNonTerminal[$production->lhs->id][$symbol->id])) {
                            $this->firstByNonTerminal[$production->lhs->id][$symbol->id] = true;
                            $changed = true;
                        }

                        break;
                    }

                    if ($symbol instanceof NonTerminal) {
                        foreach ($this->firstByNonTerminal[$symbol->id] as $terminalId => $_) {
                            if (!isset($this->firstByNonTerminal[$production->lhs->id][$terminalId])) {
                                $this->firstByNonTerminal[$production->lhs->id][$terminalId] = true;
                                $changed = true;
                            }
                        }

                        if (!$this->nullable->contains($symbol)) {
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * FIRST(symbols[start..] lookahead)
     *
     * @param list<SymbolRef> $symbols
     * @return list<int>
     */
    public function forSequenceWithLookahead(array $symbols, int $start, int $lookaheadTerminalId): array
    {
        $result = [];
        for ($i = $start; $i < count($symbols); $i++) {
            $symbol = $symbols[$i]->symbol;
            if ($symbol instanceof Terminal) {
                $result[$symbol->id] = true;
                return array_keys($result);
            }

            if ($symbol instanceof NonTerminal) {
                foreach ($this->firstByNonTerminal[$symbol->id] as $terminalId => $_) {
                    $result[$terminalId] = true;
                }

                if (!$this->nullable->contains($symbol)) {
                    return array_keys($result);
                }
            }
        }

        $result[$lookaheadTerminalId] = true;

        return array_keys($result);
    }
}
