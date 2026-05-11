<?php

declare(strict_types=1);

namespace Phison\Lalr;

use Phison\Dsl\PrecedenceDeclaration;
use Phison\Grammar\Grammar;
use Phison\Grammar\NonTerminal;
use Phison\Grammar\Production;
use Phison\Grammar\Terminal;

final class ParseTableBuilder
{
    public function build(Grammar $grammar, ItemSetCollection $collection): ParseTable
    {
        $actions = [];
        $gotos = [];
        $conflicts = [];
        $seenConflicts = [];

        foreach ($collection->states as $state) {
            foreach ($state->items as $item) {
                $production = $grammar->production($item->productionId);
                $symbol = $production->rhs[$item->position]->symbol ?? null;

                if ($symbol instanceof Terminal) {
                    $target = $state->transitionFor($symbol);
                    if ($target !== null) {
                        $this->addAction(
                            $actions,
                            $conflicts,
                            $seenConflicts,
                            $grammar,
                            $state->id,
                            $symbol->id,
                            $target + 1,
                        );
                    }
                    continue;
                }

                if ($symbol instanceof NonTerminal) {
                    $target = $state->transitionFor($symbol);
                    if ($target !== null) {
                        $gotos[$state->id][$symbol->id] = $target;
                    }
                    continue;
                }

                if ($production->id === 0 && $item->lookaheadTerminalId === $grammar->eof->id) {
                    $this->addAction($actions, $conflicts, $seenConflicts, $grammar, $state->id, $grammar->eof->id, 0);
                    continue;
                }

                $this->addAction(
                    $actions,
                    $conflicts,
                    $seenConflicts,
                    $grammar,
                    $state->id,
                    $item->lookaheadTerminalId,
                    -($production->id + 1),
                );
            }
        }

        $expected = [];
        foreach ($actions as $stateId => $row) {
            ksort($row, SORT_NUMERIC);
            $actions[$stateId] = $row;
            $expected[$stateId] = array_keys($row);
        }

        foreach ($gotos as $stateId => $row) {
            ksort($row, SORT_NUMERIC);
            $gotos[$stateId] = $row;
        }

        ksort($actions, SORT_NUMERIC);
        ksort($gotos, SORT_NUMERIC);
        ksort($expected, SORT_NUMERIC);

        return new ParseTable($actions, $gotos, $expected, $conflicts);
    }

    /**
     * @param array<int, array<int, int>> $actions
     * @param list<Conflict> $conflicts
     * @param array<string, true> $seenConflicts
     */
    private function addAction(
        array &$actions,
        array &$conflicts,
        array &$seenConflicts,
        Grammar $grammar,
        int $stateId,
        int $tokenId,
        int $incomingAction,
    ): void {
        $existingAction = $actions[$stateId][$tokenId] ?? null;
        if ($existingAction === null) {
            $actions[$stateId][$tokenId] = $incomingAction;
            return;
        }

        if ($existingAction === $incomingAction) {
            return;
        }

        $conflictKey = $stateId . ':' . $tokenId . ':' . min($existingAction, $incomingAction) . ':' . max($existingAction, $incomingAction);
        if (isset($seenConflicts[$conflictKey])) {
            return;
        }

        $seenConflicts[$conflictKey] = true;
        $resolution = $this->resolveConflict($grammar, $tokenId, $existingAction, $incomingAction);
        $conflicts[] = new Conflict(
            $stateId,
            $tokenId,
            $resolution['kind'],
            $existingAction,
            $incomingAction,
            $resolution['resolved'],
            $resolution['message'],
        );

        if (!$resolution['resolved']) {
            return;
        }

        if ($resolution['action'] === null) {
            unset($actions[$stateId][$tokenId]);
            return;
        }

        $actions[$stateId][$tokenId] = $resolution['action'];
    }

    /**
     * @return array{kind:string, resolved:bool, message:?string, action:?int}
     */
    private function resolveConflict(Grammar $grammar, int $tokenId, int $existingAction, int $incomingAction): array
    {
        if ($this->isShift($existingAction) && $this->isReduce($incomingAction)) {
            return $this->resolveShiftReduce($grammar, $tokenId, $existingAction, $incomingAction);
        }

        if ($this->isReduce($existingAction) && $this->isShift($incomingAction)) {
            return $this->resolveShiftReduce($grammar, $tokenId, $incomingAction, $existingAction);
        }

        if ($this->isReduce($existingAction) && $this->isReduce($incomingAction)) {
            return [
                'kind' => Conflict::REDUCE_REDUCE,
                'resolved' => false,
                'message' => 'reduce/reduce conflicts are not resolved implicitly',
                'action' => $existingAction,
            ];
        }

        return [
            'kind' => Conflict::OTHER,
            'resolved' => false,
            'message' => 'conflicting parser actions',
            'action' => $existingAction,
        ];
    }

    /**
     * @return array{kind:string, resolved:bool, message:?string, action:?int}
     */
    private function resolveShiftReduce(Grammar $grammar, int $tokenId, int $shiftAction, int $reduceAction): array
    {
        $token = $grammar->terminalById($tokenId);
        $production = $grammar->production(-$reduceAction - 1);
        $tokenPrecedence = $grammar->precedencesBySymbol[$token->name] ?? null;
        $productionPrecedence = $production->precedence;

        if ($tokenPrecedence === null || $productionPrecedence === null) {
            return [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => false,
                'message' => 'missing precedence on token or production',
                'action' => $shiftAction,
            ];
        }

        if ($tokenPrecedence->level > $productionPrecedence->level) {
            return [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => true,
                'message' => $token->name . ' has higher precedence than ' . $this->productionPrecedenceName($production),
                'action' => $shiftAction,
            ];
        }

        if ($tokenPrecedence->level < $productionPrecedence->level) {
            return [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => true,
                'message' => $token->name . ' has lower precedence than ' . $this->productionPrecedenceName($production),
                'action' => $reduceAction,
            ];
        }

        return match ($tokenPrecedence->associativity) {
            PrecedenceDeclaration::LEFT => [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => true,
                'message' => 'left associativity chooses reduce',
                'action' => $reduceAction,
            ],
            PrecedenceDeclaration::RIGHT => [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => true,
                'message' => 'right associativity chooses shift',
                'action' => $shiftAction,
            ],
            PrecedenceDeclaration::NONASSOC => [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => true,
                'message' => 'nonassoc removes the action',
                'action' => null,
            ],
            default => [
                'kind' => Conflict::SHIFT_REDUCE,
                'resolved' => false,
                'message' => 'precedence has no associativity',
                'action' => $shiftAction,
            ],
        };
    }

    private function isShift(int $action): bool
    {
        return $action > 0;
    }

    private function isReduce(int $action): bool
    {
        return $action < 0;
    }

    private function productionPrecedenceName(Production $production): string
    {
        return $production->precedenceSymbol ?? ('production ' . (string) $production->id);
    }
}
