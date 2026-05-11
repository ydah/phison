<?php

declare(strict_types=1);

namespace Phison\CodeGen;

use Phison\Grammar\Grammar;
use Phison\Grammar\Production;
use Phison\Grammar\SymbolRef;
use Phison\Lalr\ParseTable;

final class ParserEmitter
{
    public function emit(Grammar $grammar, ParseTable $table, CodegenOptions $options): GeneratedFile
    {
        PhpTargetProfile::forVersion($options->targetPhp);

        $namespace = $options->namespace ?? $grammar->namespace;
        $className = $options->className ?? $grammar->parserClass ?? ($grammar->name . 'Parser');
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
        ];

        if ($namespace !== null && $namespace !== '') {
            $lines[] = 'namespace ' . $namespace . ';';
            $lines[] = '';
        }

        $lines[] = 'use Phison\\Runtime\\ParseError;';
        $lines[] = 'use Phison\\Runtime\\ParserInterface;';
        $lines[] = 'use Phison\\Runtime\\SourceRange;';
        $lines[] = 'use Phison\\Runtime\\TokenStreamInterface;';
        $lines[] = '';
        $lines[] = 'final class ' . $className . ' implements ParserInterface';
        $lines[] = '{';

        foreach ($grammar->terminalsById as $terminal) {
            $lines[] = '    public const T_' . $terminal->name . ' = ' . $terminal->id . ';';
        }

        $lines[] = '';
        $lines[] = $this->constArray('TOKEN_NAMES', array_map(static fn ($terminal): string => $terminal->name, $grammar->terminalsById));
        $lines[] = $this->constArray('TOKEN_DISPLAY', array_map(static fn ($terminal): string => $terminal->displayName ?? $terminal->name, $grammar->terminalsById));
        $lines[] = $this->constArray('PRODUCTION_LENGTH', array_map(static fn (Production $production): int => $production->length(), $grammar->productions));
        $lines[] = $this->constArray('PRODUCTION_LHS', array_map(static fn (Production $production): int => $production->lhs->id, $grammar->productions));
        $lines[] = $this->constArray('EXPECTED', $table->expected);
        $lines[] = '';
        $lines[] = $this->parseMethod();
        $lines[] = '';
        array_push($lines, ...(new TableEmitter())->emit($table->actions, $table->gotos, $options->tableLayout));
        $lines[] = '';
        $lines[] = $this->expectedNamesMethod();
        $lines[] = '';
        $lines[] = $this->reduceMethod($grammar);
        $lines[] = '}';
        $lines[] = '';

        return new GeneratedFile(implode("\n", $lines));
    }

    /**
     * @param mixed $value
     */
    private function constArray(string $name, $value): string
    {
        $export = var_export($value, true);
        $export = preg_replace('/^/m', '    ', $export);

        return '    private const ' . $name . ' = ' . ltrim((string) $export) . ';';
    }

    private function parseMethod(): string
    {
        return <<<'PHP'
    public function parse(TokenStreamInterface $tokens, mixed $context = null): mixed
    {
        $stateStack = [0];
        $valueStack = [];
        $locationStack = [];
        $tokenStack = [];
        $lookahead = $tokens->current();

        while (true) {
            $state = $stateStack[array_key_last($stateStack)];
            $tokenId = $lookahead->id();
            $action = $this->action($state, $tokenId);

            if ($action === null) {
                throw new ParseError(
                    $lookahead,
                    $this->expectedNames($state),
                    $tokens->previousTokens(5),
                    $tokens->nextTokens(5),
                );
            }

            if ($action > 0) {
                $stateStack[] = $action - 1;
                $valueStack[] = $lookahead->value();
                $locationStack[] = $lookahead->location();
                $tokenStack[] = $lookahead;
                if ($tokenId !== self::T_EOF) {
                    $tokens->advance();
                    $lookahead = $tokens->current();
                }
                continue;
            }

            if ($action < 0) {
                $rule = -$action - 1;
                $length = self::PRODUCTION_LENGTH[$rule];
                $rhsValues = $length === 0 ? [] : array_slice($valueStack, -$length);
                $rhsTokens = $length === 0 ? [] : array_slice($tokenStack, -$length);
                $rhsLocations = $length === 0 ? [] : array_slice($locationStack, -$length);

                for ($i = 0; $i < $length; $i++) {
                    array_pop($stateStack);
                    array_pop($valueStack);
                    array_pop($locationStack);
                    array_pop($tokenStack);
                }

                $value = $this->reduce($rule, $rhsValues, $rhsTokens, $rhsLocations, $context);
                $currentState = $stateStack[array_key_last($stateStack)];
                $lhs = self::PRODUCTION_LHS[$rule];
                $goto = $this->gotoState($currentState, $lhs);
                if ($goto === null) {
                    throw new \LogicException('Missing goto for state ' . $currentState . ' and lhs ' . $lhs . '.');
                }

                $stateStack[] = $goto;
                $valueStack[] = $value;
                $locationStack[] = $length === 0
                    ? $lookahead->location()
                    : SourceRange::merge($rhsLocations[0] ?? null, $rhsLocations[$length - 1] ?? null);
                $tokenStack[] = null;
                continue;
            }

            $acceptLength = self::PRODUCTION_LENGTH[0];
            return $acceptLength <= 1
                ? ($valueStack[array_key_last($valueStack)] ?? null)
                : ($valueStack[count($valueStack) - $acceptLength] ?? null);
        }
    }
PHP;
    }

    private function expectedNamesMethod(): string
    {
        return <<<'PHP'
    /**
     * @return list<string>
     */
    private function expectedNames(int $state): array
    {
        $names = [];
        foreach (self::EXPECTED[$state] ?? [] as $tokenId) {
            $names[] = self::TOKEN_DISPLAY[$tokenId] ?? self::TOKEN_NAMES[$tokenId] ?? (string) $tokenId;
        }

        return $names;
    }
PHP;
    }

    private function reduceMethod(Grammar $grammar): string
    {
        $lines = [
            '    private function reduce(int $rule, array $values, array $tokens, array $locations, mixed $context): mixed',
            '    {',
            '        switch ($rule) {',
        ];

        foreach ($grammar->productions as $production) {
            if ($production->id === 0) {
                continue;
            }

            $lines[] = '            case ' . $production->id . ':';
            foreach ($this->reductionVariableLines($production) as $line) {
                $lines[] = '                ' . $line;
            }

            $lines[] = '                $yyval = null;';
            foreach ($this->indentAction($this->rewriteAction($production), 16) as $line) {
                $lines[] = $line;
            }
            $lines[] = '                return $yyval;';
        }

        $lines[] = '            default:';
        $lines[] = '                throw new \\LogicException(\'Unknown reduction rule: \' . (string) $rule);';
        $lines[] = '        }';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function reductionVariableLines(Production $production): array
    {
        $lines = [];
        foreach ($production->rhs as $index => $reference) {
            $position = $index + 1;
            $lines[] = '$v' . $position . ' = $values[' . $index . '] ?? null;';
            $lines[] = '$t' . $position . ' = $tokens[' . $index . '] ?? null;';
            $lines[] = '$loc' . $position . ' = $locations[' . $index . '] ?? null;';
            if ($reference->label !== null) {
                $lines[] = '$' . $reference->label . ' = $v' . $position . ';';
            }
        }

        if ($production->rhs === []) {
            $lines[] = '// Empty production.';
        }

        return $lines;
    }

    private function rewriteAction(Production $production): string
    {
        $code = trim((string) $production->action?->code);
        if ($code === '') {
            return '$yyval = count($values) === 1 ? $values[0] : null;';
        }

        if (preg_match('/\breturn\s+(.+);\s*$/s', $code, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new \RuntimeException('Production ' . $production->id . ' action must end with "return expression;".');
        }

        $returnOffset = $matches[0][1];
        $expression = trim($matches[1][0]);
        $prefix = trim(substr($code, 0, $returnOffset));
        $rewritten = $prefix === '' ? '' : $prefix . "\n";
        $rewritten .= '$yyval = ' . $expression . ';';

        return $rewritten;
    }

    /**
     * @return list<string>
     */
    private function indentAction(string $code, int $spaces): array
    {
        $padding = str_repeat(' ', $spaces);
        $lines = explode("\n", $code);

        return array_map(static fn (string $line): string => $padding . rtrim($line), $lines);
    }
}
