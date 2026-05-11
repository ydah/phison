<?php

declare(strict_types=1);

namespace Phison\Grammar;

use Phison\Dsl\GrammarDocument;
use Phison\Dsl\PrecedenceDeclaration;
use Phison\Dsl\ProductionDefinition;
use Phison\Dsl\RuleDefinition;
use Phison\Dsl\SymbolReference;
use Phison\Dsl\TokenDefinition;

final class GrammarNormalizer
{
    public function normalize(GrammarDocument $document): Grammar
    {
        $eof = new Terminal(0, Grammar::EOF, 'EOF');
        $terminalsByName = [Grammar::EOF => $eof];
        $terminalsById = [0 => $eof];
        $nextTerminalId = 1;

        foreach ($document->tokens as $token) {
            $this->assertUniqueToken($token, $terminalsByName);
            $terminal = new Terminal($nextTerminalId++, $token->name, $token->display);
            $terminalsByName[$token->name] = $terminal;
            $terminalsById[$terminal->id] = $terminal;
        }

        $precedencesBySymbol = $this->buildPrecedenceMap($document->precedences);

        $nonTerminalsByName = [];
        $nonTerminalsById = [];
        $accept = new NonTerminal(0, Grammar::ACCEPT);
        $nonTerminalsByName[$accept->name] = $accept;
        $nonTerminalsById[$accept->id] = $accept;
        $nextNonTerminalId = 1;

        foreach ($document->rules as $rule) {
            if (isset($nonTerminalsByName[$rule->name])) {
                throw new GrammarException('Duplicate rule: ' . $rule->name);
            }

            $nonTerminal = new NonTerminal($nextNonTerminalId++, $rule->name);
            $nonTerminalsByName[$rule->name] = $nonTerminal;
            $nonTerminalsById[$nonTerminal->id] = $nonTerminal;
        }

        if (!isset($nonTerminalsByName[$document->start])) {
            throw new GrammarException('Start symbol is not defined as a rule: ' . $document->start);
        }

        $start = $nonTerminalsByName[$document->start];
        $productions = [
            new Production(
                0,
                $accept,
                [
                    new SymbolRef($start),
                    new SymbolRef($eof),
                ],
                null,
                null,
            ),
        ];

        foreach ($document->rules as $rule) {
            $lhs = $nonTerminalsByName[$rule->name];
            foreach ($rule->productions as $productionDefinition) {
                $productions[] = $this->normalizeProduction(
                    count($productions),
                    $lhs,
                    $productionDefinition,
                    $terminalsByName,
                    $nonTerminalsByName,
                    $precedencesBySymbol,
                );
            }
        }

        $productionsByLhs = [];
        foreach ($productions as $production) {
            $productionsByLhs[$production->lhs->id][] = $production;
        }

        return new Grammar(
            $document->name,
            $document->namespace,
            $document->parserClass,
            $start,
            $eof,
            $terminalsByName,
            $terminalsById,
            $nonTerminalsByName,
            $nonTerminalsById,
            $productions,
            $productionsByLhs,
            $precedencesBySymbol,
        );
    }

    /**
     * @param array<string, Terminal> $terminalsByName
     */
    private function assertUniqueToken(TokenDefinition $token, array $terminalsByName): void
    {
        if ($token->name === Grammar::EOF) {
            throw new GrammarException('EOF is reserved and must not be declared.');
        }

        if (isset($terminalsByName[$token->name])) {
            throw new GrammarException('Duplicate token: ' . $token->name);
        }
    }

    /**
     * @param list<PrecedenceDeclaration> $declarations
     * @return array<string, Precedence>
     */
    private function buildPrecedenceMap(array $declarations): array
    {
        $result = [];
        $level = 1;
        foreach ($declarations as $declaration) {
            foreach ($declaration->symbols as $symbol) {
                if (isset($result[$symbol])) {
                    throw new GrammarException('Duplicate precedence symbol: ' . $symbol);
                }

                $result[$symbol] = new Precedence($symbol, $level, $declaration->associativity);
            }

            $level++;
        }

        return $result;
    }

    /**
     * @param array<string, Terminal> $terminalsByName
     * @param array<string, NonTerminal> $nonTerminalsByName
     * @param array<string, Precedence> $precedencesBySymbol
     */
    private function normalizeProduction(
        int $id,
        NonTerminal $lhs,
        ProductionDefinition $definition,
        array $terminalsByName,
        array $nonTerminalsByName,
        array $precedencesBySymbol,
    ): Production {
        $rhs = [];
        $labels = [];
        foreach ($definition->rhs as $symbolReference) {
            $symbol = $this->resolveSymbol($symbolReference, $terminalsByName, $nonTerminalsByName);
            if ($symbolReference->label !== null) {
                if (isset($labels[$symbolReference->label])) {
                    throw new GrammarException('Duplicate label "' . $symbolReference->label . '" in ' . $lhs->name);
                }

                $labels[$symbolReference->label] = true;
            }

            $rhs[] = new SymbolRef($symbol, $symbolReference->label);
        }

        $precedenceSymbol = $definition->precedenceSymbol ?? $this->rightmostTerminalPrecedenceSymbol($rhs, $precedencesBySymbol);
        $precedence = null;
        if ($precedenceSymbol !== null) {
            $precedence = $precedencesBySymbol[$precedenceSymbol]
                ?? throw new GrammarException('Unknown precedence symbol: ' . $precedenceSymbol);
        }

        return new Production(
            $id,
            $lhs,
            $rhs,
            $definition->action,
            $precedence,
            $precedenceSymbol,
        );
    }

    /**
     * @param array<string, Terminal> $terminalsByName
     * @param array<string, NonTerminal> $nonTerminalsByName
     */
    private function resolveSymbol(
        SymbolReference $reference,
        array $terminalsByName,
        array $nonTerminalsByName,
    ): Symbol {
        if (isset($terminalsByName[$reference->name])) {
            return $terminalsByName[$reference->name];
        }

        if (isset($nonTerminalsByName[$reference->name])) {
            return $nonTerminalsByName[$reference->name];
        }

        throw new GrammarException('Undefined symbol: ' . $reference->name);
    }

    /**
     * @param list<SymbolRef> $rhs
     * @param array<string, Precedence> $precedencesBySymbol
     */
    private function rightmostTerminalPrecedenceSymbol(array $rhs, array $precedencesBySymbol): ?string
    {
        for ($i = count($rhs) - 1; $i >= 0; $i--) {
            $symbol = $rhs[$i]->symbol;
            if ($symbol instanceof Terminal && isset($precedencesBySymbol[$symbol->name])) {
                return $symbol->name;
            }
        }

        return null;
    }
}
