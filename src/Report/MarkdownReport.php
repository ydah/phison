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
            array_push($lines, ...$this->renderConflicts($grammar, $collection, $table));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    private function renderConflicts(Grammar $grammar, ItemSetCollection $collection, ParseTable $table): array
    {
        $lines = [];
        $automaton = new AutomatonReport();
        $stateIds = [];

        foreach ($table->conflicts as $index => $conflict) {
            $stateIds[$conflict->stateId] = true;
            $token = $grammar->terminalById($conflict->tokenId)->name;
            $status = $conflict->resolved ? 'resolved' : 'unresolved';
            $lines[] = '### Conflict ' . ($index + 1);
            $lines[] = '';
            $lines[] = '- State: `' . $conflict->stateId . '`';
            $lines[] = '- Token: `' . $token . '`';
            $lines[] = '- Kind: `' . $conflict->kind . '`';
            $lines[] = '- Status: `' . $status . '`';
            $lines[] = '- Existing action: `' . $automaton->formatAction($grammar, $conflict->existingAction) . '`';
            $lines[] = '- Incoming action: `' . $automaton->formatAction($grammar, $conflict->incomingAction) . '`';
            $lines[] = '- Resolution: ' . ($conflict->resolution ?? 'none');
            $lines[] = '';
        }

        $lines[] = '## Conflict States';
        $lines[] = '';
        foreach (array_keys($stateIds) as $stateId) {
            $lines[] = '### State ' . $stateId;
            $lines[] = '';
            $lines[] = '```text';
            $lines[] = rtrim($automaton->renderState($grammar, $collection, $table, (int) $stateId));
            $lines[] = '```';
            $lines[] = '';
        }

        return $lines;
    }
}
