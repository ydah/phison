<?php

declare(strict_types=1);

namespace Example\Filter;

use Example\Filter\Generated\FilterParser;
use Example\Support\ArrayTokenStream;
use Example\Support\Token;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

final class FilterLexer
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
        $column = 1;
        $length = strlen($this->source);

        while ($offset < $length) {
            $char = $this->source[$offset];
            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                $offset++;
                $column++;
                continue;
            }

            if ($char === '"') {
                [$value, $endOffset, $endColumn] = $this->readString($offset, $column);
                $tokens[] = new Token(
                    FilterParser::T_STRING,
                    'STRING',
                    $value,
                    new SourceRange(null, $offset, $endOffset, 1, $column, 1, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            if (ctype_digit($char)) {
                [$value, $endOffset, $endColumn] = $this->readNumber($offset, $column);
                $tokens[] = new Token(
                    FilterParser::T_NUMBER,
                    'NUMBER',
                    $value,
                    new SourceRange(null, $offset, $endOffset, 1, $column, 1, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                [$tokenId, $name, $value, $endOffset, $endColumn] = $this->readWord($offset, $column);
                $tokens[] = new Token(
                    $tokenId,
                    $name,
                    $value,
                    new SourceRange(null, $offset, $endOffset, 1, $column, 1, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            $punctuation = match ($char) {
                ':' => [FilterParser::T_COLON, 'COLON'],
                '(' => [FilterParser::T_LPAREN, 'LPAREN'],
                ')' => [FilterParser::T_RPAREN, 'RPAREN'],
                '-' => [FilterParser::T_NOT, 'NOT'],
                default => null,
            };

            if ($punctuation === null) {
                throw new \RuntimeException('Unexpected filter character "' . $char . '" at column ' . $column . '.');
            }

            $tokens[] = new Token(
                $punctuation[0],
                $punctuation[1],
                $char,
                new SourceRange(null, $offset, $offset + 1, 1, $column, 1, $column + 1),
            );
            $offset++;
            $column++;
        }

        $tokens[] = new Token(
            FilterParser::T_EOF,
            'EOF',
            null,
            new SourceRange(null, $offset, $offset, 1, $column, 1, $column),
        );

        return $tokens;
    }

    /**
     * @return array{string, int, int}
     */
    private function readString(int $offset, int $column): array
    {
        $end = $offset + 1;
        $length = strlen($this->source);
        $value = '';
        $escaped = false;

        while ($end < $length) {
            $char = $this->source[$end];
            if ($escaped) {
                $value .= match ($char) {
                    '"' => '"',
                    '\\' => '\\',
                    'n' => "\n",
                    default => $char,
                };
                $escaped = false;
                $end++;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $end++;
                continue;
            }

            if ($char === '"') {
                $end++;
                return [$value, $end, $column + $end - $offset];
            }

            $value .= $char;
            $end++;
        }

        throw new \RuntimeException('Unterminated filter string at column ' . $column . '.');
    }

    /**
     * @return array{int|float, int, int}
     */
    private function readNumber(int $offset, int $column): array
    {
        preg_match('/[0-9]+(?:\.[0-9]+)?/A', substr($this->source, $offset), $matches);
        $raw = $matches[0];
        $value = str_contains($raw, '.') ? (float) $raw : (int) $raw;

        return [$value, $offset + strlen($raw), $column + strlen($raw)];
    }

    /**
     * @return array{int, string, string, int, int}
     */
    private function readWord(int $offset, int $column): array
    {
        preg_match('/[A-Za-z_][A-Za-z0-9_.-]*/A', substr($this->source, $offset), $matches);
        $word = $matches[0];
        $upper = strtoupper($word);
        $token = match ($upper) {
            'AND' => [FilterParser::T_AND, 'AND'],
            'OR' => [FilterParser::T_OR, 'OR'],
            'NOT' => [FilterParser::T_NOT, 'NOT'],
            default => [FilterParser::T_IDENT, 'IDENT'],
        };

        return [$token[0], $token[1], $word, $offset + strlen($word), $column + strlen($word)];
    }
}
