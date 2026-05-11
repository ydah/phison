<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class TableEmitter
{
    /**
     * @param array<int, array<int, int>> $actions
     * @param array<int, array<int, int>> $gotos
     * @return list<string>
     */
    public function emit(array $actions, array $gotos, string $layout, PhpTargetProfile $profile): array
    {
        $layout = $this->resolveLayout($actions, $layout);

        return match ($layout) {
            TableLayout::ARRAY => $this->emitArrayTables($actions, $gotos, $profile),
            TableLayout::SWITCH => $this->emitSwitchTables($actions, $gotos),
            TableLayout::PACKED => $this->emitPackedTables($actions, $gotos, $profile),
            default => throw new \InvalidArgumentException('Unsupported table layout: ' . $layout),
        };
    }

    /**
     * @param array<int, array<int, int>> $actions
     */
    private function resolveLayout(array $actions, string $layout): string
    {
        if (!in_array($layout, TableLayout::all(), true)) {
            throw new \InvalidArgumentException('Unsupported table layout: ' . $layout);
        }

        if ($layout !== TableLayout::HYBRID) {
            return $layout;
        }

        $stateCount = count($actions);
        $entryCount = 0;
        foreach ($actions as $row) {
            $entryCount += count($row);
        }

        if ($stateCount <= 32 && $entryCount <= 256) {
            return TableLayout::SWITCH;
        }

        if ($stateCount >= 128 || $entryCount >= 2048) {
            return TableLayout::PACKED;
        }

        return TableLayout::ARRAY;
    }

    /**
     * @param array<int, array<int, int>> $actions
     * @param array<int, array<int, int>> $gotos
     * @return list<string>
     */
    private function emitArrayTables(array $actions, array $gotos, PhpTargetProfile $profile): array
    {
        return [
            $this->constArray($profile, 'ACTION', $actions),
            $this->constArray($profile, 'GOTO', $gotos),
            '',
            <<<'PHP'
    private function action(int $state, int $token): ?int
    {
        return self::ACTION[$state][$token] ?? null;
    }
PHP,
            '',
            <<<'PHP'
    private function gotoState(int $state, int $nonTerminal): ?int
    {
        return self::GOTO[$state][$nonTerminal] ?? null;
    }
PHP,
        ];
    }

    /**
     * @param array<int, array<int, int>> $actions
     * @param array<int, array<int, int>> $gotos
     * @return list<string>
     */
    private function emitSwitchTables(array $actions, array $gotos): array
    {
        return [
            $this->switchMethod('action', 'token', $actions),
            '',
            $this->switchMethod('gotoState', 'nonTerminal', $gotos),
        ];
    }

    /**
     * @param array<int, array<int, int>> $actions
     * @param array<int, array<int, int>> $gotos
     * @return list<string>
     */
    private function emitPackedTables(array $actions, array $gotos, PhpTargetProfile $profile): array
    {
        $action = $this->pack($actions);
        $goto = $this->pack($gotos);

        return [
            $this->constArray($profile, 'ACTION_BASE', $action['base']),
            $this->constArray($profile, 'ACTION_CHECK', $action['check']),
            $this->constArray($profile, 'ACTION_VALUE', $action['value']),
            $this->constArray($profile, 'GOTO_BASE', $goto['base']),
            $this->constArray($profile, 'GOTO_CHECK', $goto['check']),
            $this->constArray($profile, 'GOTO_VALUE', $goto['value']),
            '',
            <<<'PHP'
    private function action(int $state, int $token): ?int
    {
        $index = (self::ACTION_BASE[$state] ?? 0) + $token;
        if ((self::ACTION_CHECK[$index] ?? null) !== $state) {
            return null;
        }

        return self::ACTION_VALUE[$index];
    }
PHP,
            '',
            <<<'PHP'
    private function gotoState(int $state, int $nonTerminal): ?int
    {
        $index = (self::GOTO_BASE[$state] ?? 0) + $nonTerminal;
        if ((self::GOTO_CHECK[$index] ?? null) !== $state) {
            return null;
        }

        return self::GOTO_VALUE[$index];
    }
PHP,
        ];
    }

    /**
     * @param array<int, array<int, int>> $rows
     * @return array{base: array<int, int>, check: array<int, int>, value: array<int, int>}
     */
    private function pack(array $rows): array
    {
        $base = [];
        $check = [];
        $value = [];

        foreach ($rows as $state => $row) {
            $base[$state] = $this->findBase($row, $check);
            foreach ($row as $symbol => $entry) {
                $index = $base[$state] + $symbol;
                $check[$index] = $state;
                $value[$index] = $entry;
            }
        }

        ksort($base, SORT_NUMERIC);
        ksort($check, SORT_NUMERIC);
        ksort($value, SORT_NUMERIC);

        return [
            'base' => $base,
            'check' => $check,
            'value' => $value,
        ];
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

        for ($base = 0; ; $base++) {
            foreach ($row as $symbol => $_) {
                if (isset($check[$base + $symbol])) {
                    continue 2;
                }
            }

            return $base;
        }
    }

    /**
     * @param array<int, array<int, int>> $rows
     */
    private function switchMethod(string $method, string $symbolName, array $rows): string
    {
        $lines = [
            '    private function ' . $method . '(int $state, int $' . $symbolName . '): ?int',
            '    {',
            '        return match ($state) {',
        ];

        foreach ($rows as $state => $row) {
            $lines[] = '            ' . $state . ' => match ($' . $symbolName . ') {';
            foreach ($row as $symbol => $value) {
                $lines[] = '                ' . $symbol . ' => ' . $value . ',';
            }
            $lines[] = '                default => null,';
            $lines[] = '            },';
        }

        $lines[] = '            default => null,';
        $lines[] = '        };';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    /**
     * @param mixed $value
     */
    private function constArray(PhpTargetProfile $profile, string $name, $value): string
    {
        $export = var_export($value, true);
        $export = preg_replace('/^/m', '    ', $export);

        $type = $profile->supportsTypedConstants ? 'array ' : '';

        return '    private const ' . $type . $name . ' = ' . ltrim((string) $export) . ';';
    }
}
