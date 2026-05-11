<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class Grammar
{
    public const EOF = 'EOF';
    public const ACCEPT = '$accept';

    /**
     * @param array<string, Terminal> $terminalsByName
     * @param array<int, Terminal> $terminalsById
     * @param array<string, NonTerminal> $nonTerminalsByName
     * @param array<int, NonTerminal> $nonTerminalsById
     * @param list<Production> $productions
     * @param array<int, list<Production>> $productionsByLhs
     * @param array<string, Precedence> $precedencesBySymbol
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $namespace,
        public readonly ?string $parserClass,
        public readonly NonTerminal $start,
        public readonly Terminal $eof,
        public readonly array $terminalsByName,
        public readonly array $terminalsById,
        public readonly array $nonTerminalsByName,
        public readonly array $nonTerminalsById,
        public readonly array $productions,
        public readonly array $productionsByLhs,
        public readonly array $precedencesBySymbol,
    ) {
    }

    public function terminal(string $name): Terminal
    {
        return $this->terminalsByName[$name]
            ?? throw new GrammarException('Unknown terminal: ' . $name);
    }

    public function terminalById(int $id): Terminal
    {
        return $this->terminalsById[$id]
            ?? throw new GrammarException('Unknown terminal id: ' . (string) $id);
    }

    public function nonTerminal(string $name): NonTerminal
    {
        return $this->nonTerminalsByName[$name]
            ?? throw new GrammarException('Unknown non-terminal: ' . $name);
    }

    /**
     * @return list<Production>
     */
    public function productionsFor(NonTerminal $nonTerminal): array
    {
        return $this->productionsByLhs[$nonTerminal->id] ?? [];
    }

    public function production(int $id): Production
    {
        return $this->productions[$id]
            ?? throw new GrammarException('Unknown production id: ' . (string) $id);
    }
}
