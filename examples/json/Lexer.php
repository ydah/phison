<?php

declare(strict_types=1);

namespace Example\Json;

use Example\Json\Generated\JsonParser;
use Example\Support\ArrayTokenStream;
use Example\Support\Token;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

final class JsonLexer
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

            if ($char === '"') {
                [$raw, $value, $endOffset, $endColumn] = $this->readString($offset, $column);
                $tokens[] = new Token(
                    JsonParser::T_STRING,
                    'STRING',
                    $value,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
                );
                unset($raw);
                $column = $endColumn;
                $offset = $endOffset;
                continue;
            }

            if ($char === '-' || ctype_digit($char)) {
                [$value, $endOffset, $endColumn] = $this->readNumber($offset, $column);
                $tokens[] = new Token(
                    JsonParser::T_NUMBER,
                    'NUMBER',
                    $value,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
                );
                $column = $endColumn;
                $offset = $endOffset;
                continue;
            }

            $literal = $this->literalToken($offset);
            if ($literal !== null) {
                [$tokenId, $name, $value] = $literal;
                $endOffset = $offset + strlen($value);
                $tokens[] = new Token(
                    $tokenId,
                    $name,
                    $value,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $column + strlen($value)),
                );
                $column += strlen($value);
                $offset = $endOffset;
                continue;
            }

            $punctuation = match ($char) {
                '{' => [JsonParser::T_LBRACE, 'LBRACE'],
                '}' => [JsonParser::T_RBRACE, 'RBRACE'],
                '[' => [JsonParser::T_LBRACKET, 'LBRACKET'],
                ']' => [JsonParser::T_RBRACKET, 'RBRACKET'],
                ':' => [JsonParser::T_COLON, 'COLON'],
                ',' => [JsonParser::T_COMMA, 'COMMA'],
                default => null,
            };

            if ($punctuation === null) {
                throw new \RuntimeException('Unexpected JSON character "' . $char . '" at column ' . $column . '.');
            }

            $tokens[] = new Token(
                $punctuation[0],
                $punctuation[1],
                $char,
                new SourceRange(null, $offset, $offset + 1, $line, $column, $line, $column + 1),
            );
            $offset++;
            $column++;
        }

        $tokens[] = new Token(
            JsonParser::T_EOF,
            'EOF',
            null,
            new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
        );

        return $tokens;
    }

    /**
     * @return array{string, string, int, int}
     */
    private function readString(int $offset, int $column): array
    {
        $end = $offset + 1;
        $length = strlen($this->source);
        $escaped = false;
        while ($end < $length) {
            $char = $this->source[$end];
            if ($escaped) {
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
                $raw = substr($this->source, $offset, $end - $offset);
                $value = json_decode($raw, true);
                if (!is_string($value)) {
                    throw new \RuntimeException('Invalid JSON string at column ' . $column . '.');
                }

                return [$raw, $value, $end, $column + strlen($raw)];
            }

            if ($char === "\n") {
                throw new \RuntimeException('Unterminated JSON string at column ' . $column . '.');
            }

            $end++;
        }

        throw new \RuntimeException('Unterminated JSON string at column ' . $column . '.');
    }

    /**
     * @return array{int|float, int, int}
     */
    private function readNumber(int $offset, int $column): array
    {
        if (preg_match('/-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A', substr($this->source, $offset), $matches) !== 1) {
            throw new \RuntimeException('Invalid JSON number at column ' . $column . '.');
        }

        $raw = $matches[0];
        $value = str_contains($raw, '.') || str_contains($raw, 'e') || str_contains($raw, 'E')
            ? (float) $raw
            : (int) $raw;

        return [$value, $offset + strlen($raw), $column + strlen($raw)];
    }

    /**
     * @return array{int, string, string}|null
     */
    private function literalToken(int $offset): ?array
    {
        foreach ([
            'true' => [JsonParser::T_TRUE, 'TRUE'],
            'false' => [JsonParser::T_FALSE, 'FALSE'],
            'null' => [JsonParser::T_NULL, 'NULL'],
        ] as $literal => $token) {
            if (substr($this->source, $offset, strlen($literal)) === $literal) {
                return [$token[0], $token[1], $literal];
            }
        }

        return null;
    }
}
