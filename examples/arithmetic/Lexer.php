<?php

declare(strict_types=1);

namespace Example\Arithmetic;

use Example\Arithmetic\Generated\ArithmeticParser;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

final class ArithmeticLexer
{
    public function __construct(
        private readonly string $source,
    ) {
    }

    public function tokens(): TokenStreamInterface
    {
        return new ArrayTokenStream($this->tokenize());
    }

    /**
     * @return non-empty-list<TokenInterface>
     */
    private function tokenize(): array
    {
        $tokens = [];
        $offset = 0;
        $line = 1;
        $column = 1;
        $length = strlen($this->source);

        while ($offset < $length) {
            $char = $this->source[$offset];
            if ($char === ' ' || $char === "\t" || $char === "\r") {
                $offset++;
                $column++;
                continue;
            }

            if ($char === "\n") {
                $offset++;
                $line++;
                $column = 1;
                continue;
            }

            if (ctype_digit($char)) {
                $startOffset = $offset;
                $startColumn = $column;
                $value = '';
                while ($offset < $length && ctype_digit($this->source[$offset])) {
                    $value .= $this->source[$offset];
                    $offset++;
                    $column++;
                }

                $tokens[] = new Token(
                    ArithmeticParser::T_NUMBER,
                    'NUMBER',
                    $value,
                    new SourceRange(null, $startOffset, $offset, $line, $startColumn, $line, $column),
                );
                continue;
            }

            $token = match ($char) {
                '+' => [ArithmeticParser::T_PLUS, 'PLUS'],
                '-' => [ArithmeticParser::T_MINUS, 'MINUS'],
                '*' => [ArithmeticParser::T_STAR, 'STAR'],
                '/' => [ArithmeticParser::T_SLASH, 'SLASH'],
                '(' => [ArithmeticParser::T_LPAREN, 'LPAREN'],
                ')' => [ArithmeticParser::T_RPAREN, 'RPAREN'],
                default => null,
            };

            if ($token === null) {
                throw new \RuntimeException('Unexpected character "' . $char . '" at column ' . $column . '.');
            }

            $tokens[] = new Token(
                $token[0],
                $token[1],
                $char,
                new SourceRange(null, $offset, $offset + 1, $line, $column, $line, $column + 1),
            );
            $offset++;
            $column++;
        }

        $tokens[] = new Token(
            ArithmeticParser::T_EOF,
            'EOF',
            null,
            new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
        );

        return $tokens;
    }
}

final class Token implements TokenInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly mixed $value,
        private readonly SourceRange $location,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function location(): SourceRange
    {
        return $this->location;
    }
}

final class ArrayTokenStream implements TokenStreamInterface
{
    /** @param non-empty-list<TokenInterface> $tokens */
    public function __construct(
        private readonly array $tokens,
        private int $index = 0,
    ) {
    }

    public function current(): TokenInterface
    {
        return $this->tokens[$this->index] ?? $this->tokens[count($this->tokens) - 1];
    }

    public function advance(): void
    {
        if ($this->index < count($this->tokens) - 1) {
            $this->index++;
        }
    }

    public function previousTokens(int $count): array
    {
        $start = max(0, $this->index - $count);

        return array_values(array_slice($this->tokens, $start, $this->index - $start));
    }

    public function nextTokens(int $count): array
    {
        return array_values(array_slice($this->tokens, $this->index + 1, $count));
    }
}
