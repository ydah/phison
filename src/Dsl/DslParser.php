<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class DslParser
{
    public function parseFile(string $path): GrammarDocument
    {
        $source = file_get_contents($path);
        if ($source === false) {
            throw new \RuntimeException('Unable to read grammar file: ' . $path);
        }

        return $this->parse($source, $path);
    }

    public function parse(string $source, ?string $file = null): GrammarDocument
    {
        if (str_contains($source, '%%')) {
            return (new YaccStyleParser())->parse($source, $file);
        }

        $offset = 0;
        $name = null;
        $namespace = null;
        $parserClass = null;
        $start = null;
        $tokens = [];
        $precedences = [];
        $rules = [];

        while (true) {
            $this->skipIgnorable($source, $offset);
            if ($offset >= strlen($source)) {
                break;
            }

            $keyword = $this->readIdentifier($source, $offset, $file);
            match ($keyword) {
                'grammar' => $name = $this->readIdentifierAfterSpace($source, $offset, $file),
                'namespace' => $namespace = $this->readNamespaceAfterSpace($source, $offset, $file),
                'parser' => $parserClass = $this->readIdentifierAfterSpace($source, $offset, $file),
                'start' => $start = $this->readIdentifierAfterSpace($source, $offset, $file),
                'token' => $tokens[] = $this->readTokenDefinition($source, $offset, $file),
                'precedence' => $precedences[] = $this->readPrecedence($source, $offset, $file),
                'rule' => $rules[] = $this->readRule($source, $offset, $file),
                default => throw DslParseException::at('Unexpected top-level keyword "' . $keyword . '".', $source, $offset, $file),
            };
        }

        if ($name === null) {
            throw DslParseException::at('Missing grammar declaration.', $source, 0, $file);
        }

        if ($start === null) {
            throw DslParseException::at('Missing start declaration.', $source, 0, $file);
        }

        if ($rules === []) {
            throw DslParseException::at('At least one rule is required.', $source, 0, $file);
        }

        return new GrammarDocument(
            $name,
            $namespace,
            $parserClass,
            $start,
            $tokens,
            $precedences,
            $rules,
        );
    }

    private function readTokenDefinition(string $source, int &$offset, ?string $file): TokenDefinition
    {
        $name = $this->readIdentifierAfterSpace($source, $offset, $file);
        $display = null;

        $saved = $offset;
        $this->skipInlineWhitespace($source, $offset);
        if ($this->tryReadWord($source, $offset, 'display')) {
            $this->skipInlineWhitespace($source, $offset);
            $display = $this->readQuotedString($source, $offset, $file);
        } else {
            $offset = $saved;
        }

        return new TokenDefinition($name, $display);
    }

    private function readPrecedence(string $source, int &$offset, ?string $file): PrecedenceDeclaration
    {
        $associativity = $this->readIdentifierAfterSpace($source, $offset, $file);
        $valid = [
            PrecedenceDeclaration::LEFT,
            PrecedenceDeclaration::RIGHT,
            PrecedenceDeclaration::NONASSOC,
            PrecedenceDeclaration::PRECEDENCE,
        ];
        if (!in_array($associativity, $valid, true)) {
            throw DslParseException::at('Invalid associativity "' . $associativity . '".', $source, $offset, $file);
        }

        $symbols = [];
        while (true) {
            $this->skipInlineWhitespace($source, $offset);
            if ($offset >= strlen($source) || $source[$offset] === "\n" || $source[$offset] === "\r") {
                break;
            }

            $symbols[] = $this->readIdentifier($source, $offset, $file);
        }

        if ($symbols === []) {
            throw DslParseException::at('Precedence declaration requires at least one symbol.', $source, $offset, $file);
        }

        return new PrecedenceDeclaration($associativity, $symbols);
    }

    private function readRule(string $source, int &$offset, ?string $file): RuleDefinition
    {
        $name = $this->readIdentifierAfterSpace($source, $offset, $file);
        $this->skipIgnorable($source, $offset);
        $body = $this->readBalancedBlock($source, $offset, $file);
        $productions = $this->parseRuleBody($body, $file);

        if ($productions === []) {
            throw DslParseException::at('Rule "' . $name . '" has no alternatives.', $source, $offset, $file);
        }

        return new RuleDefinition($name, $productions);
    }

    /**
     * @return list<ProductionDefinition>
     */
    private function parseRuleBody(string $body, ?string $file): array
    {
        $offset = 0;
        $productions = [];

        while (true) {
            $this->skipIgnorable($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            if ($body[$offset] === '|') {
                $offset++;
                $this->skipIgnorable($body, $offset);
            }

            $marker = $this->findPhpActionMarker($body, $offset);
            if ($marker === null) {
                throw DslParseException::at('Expected "=> php { ... }" action.', $body, $offset, $file);
            }

            $rhsText = trim(substr($body, $offset, $marker - $offset));
            $offset = $marker + 2;
            $this->skipIgnorable($body, $offset);
            if (!$this->tryReadWord($body, $offset, 'php')) {
                throw DslParseException::at('Expected php action language.', $body, $offset, $file);
            }

            $this->skipIgnorable($body, $offset);
            $action = $this->readBalancedBlock($body, $offset, $file);
            $productions[] = $this->parseProduction($rhsText, $action, $body, $marker, $file);
        }

        return $productions;
    }

    private function parseProduction(string $rhsText, string $action, string $source, int $offset, ?string $file): ProductionDefinition
    {
        $parts = $rhsText === '' ? [] : preg_split('/\s+/', $rhsText);
        if ($parts === false) {
            $parts = [];
        }

        $rhs = [];
        $precedenceSymbol = null;
        $count = count($parts);
        for ($i = 0; $i < $count; $i++) {
            $part = $parts[$i];
            if ($part === '') {
                continue;
            }

            if ($part === '%prec') {
                if ($i + 1 >= $count) {
                    throw DslParseException::at('%prec requires a symbol.', $source, $offset, $file);
                }

                $precedenceSymbol = $parts[++$i];
                if (!$this->isIdentifier($precedenceSymbol)) {
                    throw DslParseException::at('Invalid precedence symbol "' . $precedenceSymbol . '".', $source, $offset, $file);
                }

                if ($i + 1 < $count) {
                    throw DslParseException::at('%prec must appear after all RHS symbols.', $source, $offset, $file);
                }

                break;
            }

            $label = null;
            $name = $part;
            if (str_contains($part, '=')) {
                [$label, $name] = explode('=', $part, 2);
                if (!$this->isIdentifier($label)) {
                    throw DslParseException::at('Invalid label "' . $label . '".', $source, $offset, $file);
                }
            }

            if (!$this->isIdentifier($name)) {
                throw DslParseException::at('Invalid symbol reference "' . $name . '".', $source, $offset, $file);
            }

            $rhs[] = new SymbolReference($name, $label);
        }

        return new ProductionDefinition($rhs, new ActionCode(trim($action)), $precedenceSymbol);
    }

    private function findPhpActionMarker(string $source, int $offset): ?int
    {
        $length = strlen($source);
        while ($offset < $length - 1) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $this->skipString($source, $offset, $char);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '/') {
                $this->skipLineComment($source, $offset);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '*') {
                $this->skipBlockComment($source, $offset);
                continue;
            }

            if ($char === '=' && $source[$offset + 1] === '>') {
                return $offset;
            }

            $offset++;
        }

        return null;
    }

    private function readIdentifierAfterSpace(string $source, int &$offset, ?string $file): string
    {
        $this->requireWhitespace($source, $offset, $file);
        $this->skipInlineWhitespace($source, $offset);

        return $this->readIdentifier($source, $offset, $file);
    }

    private function readNamespaceAfterSpace(string $source, int &$offset, ?string $file): string
    {
        $this->requireWhitespace($source, $offset, $file);
        $this->skipInlineWhitespace($source, $offset);
        if (!preg_match('/\G[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*/A', $source, $match, 0, $offset)) {
            throw DslParseException::at('Expected namespace name.', $source, $offset, $file);
        }

        $offset += strlen($match[0]);

        return $match[0];
    }

    private function readIdentifier(string $source, int &$offset, ?string $file): string
    {
        if (!preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/A', $source, $match, 0, $offset)) {
            throw DslParseException::at('Expected identifier.', $source, $offset, $file);
        }

        $offset += strlen($match[0]);

        return $match[0];
    }

    private function readQuotedString(string $source, int &$offset, ?string $file): string
    {
        if (($source[$offset] ?? '') !== '"') {
            throw DslParseException::at('Expected quoted string.', $source, $offset, $file);
        }

        $offset++;
        $value = '';
        while ($offset < strlen($source)) {
            $char = $source[$offset++];
            if ($char === '"') {
                return $value;
            }

            if ($char === '\\') {
                if ($offset >= strlen($source)) {
                    break;
                }

                $escaped = $source[$offset++];
                $value .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '"' => '"',
                    '\\' => '\\',
                    default => $escaped,
                };
                continue;
            }

            $value .= $char;
        }

        throw DslParseException::at('Unterminated string literal.', $source, $offset, $file);
    }

    private function readBalancedBlock(string $source, int &$offset, ?string $file): string
    {
        if (($source[$offset] ?? '') !== '{') {
            throw DslParseException::at('Expected "{".', $source, $offset, $file);
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if ($char === '"' || $char === "'") {
                $this->skipString($source, $offset, $char);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '/') {
                $this->skipLineComment($source, $offset);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '*') {
                $this->skipBlockComment($source, $offset);
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

                $offset++;
                continue;
            }

            $offset++;
        }

        throw DslParseException::at('Unterminated block.', $source, $start, $file);
    }

    private function skipIgnorable(string $source, int &$offset): void
    {
        while ($offset < strlen($source)) {
            $char = $source[$offset];
            if (ctype_space($char)) {
                $offset++;
                continue;
            }

            if ($char === '#') {
                $this->skipLineComment($source, $offset);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '/') {
                $this->skipLineComment($source, $offset);
                continue;
            }

            if ($char === '/' && ($source[$offset + 1] ?? '') === '*') {
                $this->skipBlockComment($source, $offset);
                continue;
            }

            break;
        }
    }

    private function skipInlineWhitespace(string $source, int &$offset): void
    {
        while ($offset < strlen($source) && ($source[$offset] === ' ' || $source[$offset] === "\t")) {
            $offset++;
        }
    }

    private function requireWhitespace(string $source, int $offset, ?string $file): void
    {
        if ($offset >= strlen($source) || !ctype_space($source[$offset])) {
            throw DslParseException::at('Expected whitespace.', $source, $offset, $file);
        }
    }

    private function tryReadWord(string $source, int &$offset, string $word): bool
    {
        $length = strlen($word);
        if (substr($source, $offset, $length) !== $word) {
            return false;
        }

        $next = $source[$offset + $length] ?? '';
        if ($next !== '' && preg_match('/[A-Za-z0-9_]/', $next) === 1) {
            return false;
        }

        $offset += $length;

        return true;
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

    private function skipLineComment(string $source, int &$offset): void
    {
        while ($offset < strlen($source) && $source[$offset] !== "\n") {
            $offset++;
        }
    }

    private function skipBlockComment(string $source, int &$offset): void
    {
        $offset += 2;
        while ($offset < strlen($source) - 1) {
            if ($source[$offset] === '*' && $source[$offset + 1] === '/') {
                $offset += 2;
                return;
            }

            $offset++;
        }
    }

    private function isIdentifier(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }
}
