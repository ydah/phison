<?php

declare(strict_types=1);

namespace Phison\Report;

use Phison\Grammar\Grammar;
use Phison\Grammar\Production;
use Phison\Lalr\ItemSetCollection;
use Phison\Lalr\LrItem;
use Phison\Lalr\ParseTable;

final class AutomatonReport
{
    public function render(Grammar $grammar, ItemSetCollection $collection, ParseTable $table): string
    {
        $chunks = [];
        foreach ($collection->states as $state) {
            $chunks[] = rtrim($this->renderState($grammar, $collection, $table, $state->id));
        }

        return implode("\n\n", $chunks) . "\n";
    }

    public function renderState(Grammar $grammar, ItemSetCollection $collection, ParseTable $table, int $stateId): string
    {
        $state = $collection->state($stateId);
        $lines = ['State ' . $state->id, ''];
        foreach ($state->items as $item) {
            $lines[] = '  ' . $this->formatItem($grammar, $item);
        }

        $lines[] = '';
        $lines[] = 'Actions:';
        foreach ($table->actions[$stateId] ?? [] as $tokenId => $action) {
            $lines[] = '  ' . $grammar->terminalById($tokenId)->name . '  ' . $this->formatAction($grammar, $action);
        }

        $lines[] = '';
        $lines[] = 'Gotos:';
        foreach ($table->gotos[$stateId] ?? [] as $nonTerminalId => $target) {
            $name = $grammar->nonTerminalsById[$nonTerminalId]->name ?? (string) $nonTerminalId;
            $lines[] = '  ' . $name . '  ' . $target;
        }

        return implode("\n", $lines) . "\n";
    }

    public function formatItem(Grammar $grammar, LrItem $item): string
    {
        $production = $grammar->production($item->productionId);
        $rhs = [];
        for ($i = 0; $i <= $production->length(); $i++) {
            if ($i === $item->position) {
                $rhs[] = '.';
            }

            if (isset($production->rhs[$i])) {
                $rhs[] = $production->rhs[$i]->symbol->name;
            }
        }

        return $production->lhs->name . ' -> ' . implode(' ', $rhs)
            . ' [' . $grammar->terminalById($item->lookaheadTerminalId)->name . ']';
    }

    public function formatAction(Grammar $grammar, int $action): string
    {
        if ($action > 0) {
            return 'shift ' . (string) ($action - 1);
        }

        if ($action < 0) {
            $production = $grammar->production(-$action - 1);
            return 'reduce ' . $this->formatProduction($production);
        }

        return 'accept';
    }

    private function formatProduction(Production $production): string
    {
        return $production->format();
    }
}
