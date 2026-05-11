<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class YaccStyleParser
{
    public function parse(string $source, ?string $file = null): GrammarDocument
    {
        $sections = explode('%%', $source, 3);
        if (count($sections) < 2) {
            throw DslParseException::at('Expected %% before yacc-style rules.', $source, 0, $file);
        }

        [$declarations, $rulesSource] = $sections;
        $name = null;
        $namespace = null;
        $parserClass = null;
        $start = null;
        $tokens = [];
        $precedences = [];

        foreach (preg_split('/\R/', $declarations) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? $line);
            if ($line === '') {
                continue;
            }

            $parts = $this->splitDeclaration($line);
            $directive = array_shift($parts);
            match ($directive) {
                '%name', '%grammar' => $name = $this->singleValue($parts, $line),
                '%namespace' => $namespace = $this->singleValue($parts, $line),
                '%parser' => $parserClass = $this->singleValue($parts, $line),
                '%start' => $start = $this->singleValue($parts, $line),
                '%token' => array_push($tokens, ...$this->tokenDefinitions($parts)),
                '%left' => $precedences[] = new PrecedenceDeclaration(PrecedenceDeclaration::LEFT, $this->symbolList($parts, $line)),
                '%right' => $precedences[] = new PrecedenceDeclaration(PrecedenceDeclaration::RIGHT, $this->symbolList($parts, $line)),
                '%nonassoc' => $precedences[] = new PrecedenceDeclaration(PrecedenceDeclaration::NONASSOC, $this->symbolList($parts, $line)),
                '%precedence' => $precedences[] = new PrecedenceDeclaration(PrecedenceDeclaration::PRECEDENCE, $this->symbolList($parts, $line)),
                default => throw DslParseException::at('Unknown yacc-style directive "' . (string) $directive . '".', $source, strpos($source, $line) ?: 0, $file),
            };
        }

        if ($name === null) {
            $name = $parserClass !== null ? preg_replace('/Parser$/', '', $parserClass) ?: $parserClass : 'Grammar';
        }

        if ($start === null) {
            throw DslParseException::at('Missing %start declaration.', $source, 0, $file);
        }

        return new GrammarDocument(
            $name,
            $namespace,
            $parserClass,
            $start,
            $tokens,
            $precedences,
            $this->parseRules($rulesSource, $file),
        );
    }

    /**
     * @return list<string>
     */
    private function splitDeclaration(string $line): array
    {
        preg_match_all('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\\S+/', $line, $matches);

        return array_map(
            static fn (string $part): string => str_starts_with($part, '"') ? stripcslashes(substr($part, 1, -1)) : $part,
            $matches[0],
        );
    }

    /**
     * @param list<string> $parts
     */
    private function singleValue(array $parts, string $line): string
    {
        if (count($parts) !== 1) {
            throw new DslParseException('Expected exactly one value in declaration: ' . $line);
        }

        return $parts[0];
    }

    /**
     * @param list<string> $parts
     * @return list<TokenDefinition>
     */
    private function tokenDefinitions(array $parts): array
    {
        if ($parts === []) {
            throw new DslParseException('%token requires at least one token name.');
        }

        $tokens = [];
        for ($i = 0; $i < count($parts); $i++) {
            $name = $parts[$i];
            $display = null;
            if (($parts[$i + 1] ?? null) !== null && !preg_match('/^[A-Z_][A-Z0-9_]*$/', $parts[$i + 1])) {
                $display = $parts[++$i];
            }

            $tokens[] = new TokenDefinition($name, $display);
        }

        return $tokens;
    }

    /**
     * @param list<string> $parts
     * @return non-empty-list<string>
     */
    private function symbolList(array $parts, string $line): array
    {
        if ($parts === []) {
            throw new DslParseException('Expected at least one symbol in declaration: ' . $line);
        }

        return $parts;
    }

    /**
     * @return list<RuleDefinition>
     */
    private function parseRules(string $source, ?string $file): array
    {
        $offset = 0;
        $rules = [];
        while (true) {
            $this->skipWhitespace($source, $offset);
            if ($offset >= strlen($source)) {
                break;
            }

            $name = $this->readIdentifier($source, $offset, $file);
            $this->skipWhitespace($source, $offset);
            $this->expect($source, $offset, ':', $file);
            $rules[] = new RuleDefinition($name, $this->readAlternatives($source, $offset, $file));
        }

        return $rules;
    }

    /**
     * @return list<ProductionDefinition>
     */
    private function readAlternatives(string $source, int &$offset, ?string $file): array
    {
        $productions = [];
        do {
            $this->skipWhitespace($source, $offset);
            if (($source[$offset] ?? '') === '|') {
                $offset++;
                $this->skipWhitespace($source, $offset);
            }

            $symbols = [];
            $precedence = null;
            while ($offset < strlen($source)) {
                $this->skipWhitespace($source, $offset);
                $char = $source[$offset] ?? '';
                if ($char === '{') {
                    break;
                }

                if ($char === ';') {
                    throw DslParseException::at('Yacc-style alternatives require an action block.', $source, $offset, $file);
                }

                $part = $this->readIdentifierOrDirective($source, $offset, $file);
                if ($part === '%prec') {
                    $this->skipWhitespace($source, $offset);
                    $precedence = $this->readIdentifier($source, $offset, $file);
                    continue;
                }

                $symbols[] = new SymbolReference($part);
            }

            $action = $this->rewriteDollarReferences(trim($this->readBalancedBlock($source, $offset, $file)));
            $productions[] = new ProductionDefinition($symbols, new ActionCode($action), $precedence);
            $this->skipWhitespace($source, $offset);
        } while (($source[$offset] ?? '') === '|');

        $this->expect($source, $offset, ';', $file);

        return $productions;
    }

    private function rewriteDollarReferences(string $action): string
    {
        return preg_replace('/\\$(\\d+)/', '\\$v$1', $action) ?? $action;
    }

    private function readIdentifier(string $source, int &$offset, ?string $file): string
    {
        if (!preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/A', $source, $match, 0, $offset)) {
            throw DslParseException::at('Expected identifier.', $source, $offset, $file);
        }

        $offset += strlen($match[0]);

        return $match[0];
    }

    private function readIdentifierOrDirective(string $source, int &$offset, ?string $file): string
    {
        if (substr($source, $offset, 5) === '%prec') {
            $offset += 5;
            return '%prec';
        }

        return $this->readIdentifier($source, $offset, $file);
    }

    private function readBalancedBlock(string $source, int &$offset, ?string $file): string
    {
        $this->expect($source, $offset, '{', $file);
        $start = $offset;
        $depth = 1;
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $this->skipString($source, $offset, $char);
                continue;
            }

            if ($char === '{') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $body = substr($source, $start, $offset - $start);
                    $offset++;

                    return $body;
                }
            }

            $offset++;
        }

        throw DslParseException::at('Unterminated action block.', $source, $start, $file);
    }

    private function expect(string $source, int &$offset, string $expected, ?string $file): void
    {
        if (($source[$offset] ?? '') !== $expected) {
            throw DslParseException::at('Expected "' . $expected . '".', $source, $offset, $file);
        }

        $offset++;
    }

    private function skipWhitespace(string $source, int &$offset): void
    {
        while ($offset < strlen($source)) {
            if (ctype_space($source[$offset])) {
                $offset++;
                continue;
            }

            if ($source[$offset] === '#') {
                while ($offset < strlen($source) && $source[$offset] !== "\n") {
                    $offset++;
                }
                continue;
            }

            break;
        }
    }

    private function skipString(string $source, int &$offset, string $quote): void
    {
        $offset++;
        while ($offset < strlen($source)) {
            $char = $source[$offset++];
            if ($char === '\\') {
                $offset++;
                continue;
            }

            if ($char === $quote) {
                return;
            }
        }
    }
}
