<?php

declare(strict_types=1);

namespace Phison\Report;

use Phison\Grammar\Grammar;
use Phison\Lalr\ItemSetCollection;
use Phison\Lalr\ParseTable;

final class MarkdownReport
{
    public function render(Grammar $grammar, ItemSetCollection $collection, ParseTable $table): string
    {
        $lines = [
            '# Parser Report: ' . $grammar->name,
            '',
            '- Canonical LR(1) states: ' . $collection->canonicalStateCount,
            '- LALR states: ' . count($collection->states),
            '- ACTION entries: ' . $table->actionCount(),
            '- GOTO entries: ' . $table->gotoCount(),
            '- Conflicts: ' . count($table->conflicts),
            '- Unresolved conflicts: ' . $table->unresolvedConflictCount(),
            '',
            '## Tokens',
            '',
        ];

        foreach ($grammar->terminalsById as $terminal) {
            $display = $terminal->displayName ?? $terminal->name;
            $lines[] = '- `' . $terminal->id . '` `' . $terminal->name . '` display `' . $display . '`';
        }

        $lines[] = '';
        $lines[] = '## Non-terminals';
        $lines[] = '';
        foreach ($grammar->nonTerminalsById as $nonTerminal) {
            $lines[] = '- `' . $nonTerminal->id . '` `' . $nonTerminal->name . '`';
        }

        $lines[] = '';
        $lines[] = '## Productions';
        $lines[] = '';
        foreach ($grammar->productions as $production) {
            $lines[] = '- `' . $production->id . '` ' . $production->format();
        }

        $lines[] = '';
        $lines[] = '## Precedence';
        $lines[] = '';
        if ($grammar->precedencesBySymbol === []) {
            $lines[] = '_None._';
        } else {
            foreach ($grammar->precedencesBySymbol as $precedence) {
                $lines[] = '- level `' . $precedence->level . '` `' . $precedence->symbol . '` `' . $precedence->associativity . '`';
            }
        }

        $lines[] = '';
        $lines[] = '## Conflicts';
        $lines[] = '';
        if ($table->conflicts === []) {
            $lines[] = '_None._';
        } else {
            foreach ($table->conflicts as $conflict) {
                $token = $grammar->terminalById($conflict->tokenId)->name;
                $status = $conflict->resolved ? 'resolved' : 'unresolved';
                $lines[] = '- state `' . $conflict->stateId . '`, token `' . $token . '`, '
                    . $conflict->kind . ', ' . $status . ': ' . ($conflict->resolution ?? '');
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
