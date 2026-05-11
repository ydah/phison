<?php

declare(strict_types=1);

namespace Phison\Analysis;

use Phison\Grammar\Grammar;
use Phison\Grammar\NonTerminal;

final class NullableSet
{
    /** @var array<int, true> */
    private array $nullable = [];

    public function __construct(Grammar $grammar)
    {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($grammar->productions as $production) {
                if (isset($this->nullable[$production->lhs->id])) {
                    continue;
                }

                $isNullable = true;
                foreach ($production->rhs as $reference) {
                    $symbol = $reference->symbol;
                    if ($symbol->isTerminal()) {
                        $isNullable = false;
                        break;
                    }

                    if (!$symbol instanceof NonTerminal || !isset($this->nullable[$symbol->id])) {
                        $isNullable = false;
                        break;
                    }
                }

                if ($isNullable) {
                    $this->nullable[$production->lhs->id] = true;
                    $changed = true;
                }
            }
        }
    }

    public function contains(NonTerminal $nonTerminal): bool
    {
        return isset($this->nullable[$nonTerminal->id]);
    }
}
