<?php

declare(strict_types=1);

namespace Example\Config;

use Example\Config\Generated\ConfigParser;
use Example\Support\ArrayTokenStream;
use Example\Support\Token;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

final class ConfigLexer
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

            if ($char === '#' || $char === ';') {
                while ($offset < $length && $this->source[$offset] !== "\n") {
                    $offset++;
                    $column++;
                }
                continue;
            }

            if ($char === "\n") {
                $tokens[] = new Token(
                    ConfigParser::T_NEWLINE,
                    'NEWLINE',
                    "\n",
                    new SourceRange(null, $offset, $offset + 1, $line, $column, $line + 1, 1),
                );
                $offset++;
                $line++;
                $column = 1;
                continue;
            }

            if ($char === '[') {
                [$name, $endOffset, $endColumn] = $this->readSection($offset, $column);
                $tokens[] = new Token(
                    ConfigParser::T_SECTION,
                    'SECTION',
                    $name,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            if ($char === '"') {
                [$value, $endOffset, $endColumn] = $this->readString($offset, $column);
                $tokens[] = new Token(
                    ConfigParser::T_STRING,
                    'STRING',
                    $value,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            if ($char === '-' || ctype_digit($char)) {
                [$value, $endOffset, $endColumn] = $this->readNumber($offset, $column);
                $tokens[] = new Token(
                    ConfigParser::T_NUMBER,
                    'NUMBER',
                    $value,
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
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
                    new SourceRange(null, $offset, $endOffset, $line, $column, $line, $endColumn),
                );
                $offset = $endOffset;
                $column = $endColumn;
                continue;
            }

            if ($char === '=') {
                $tokens[] = new Token(
                    ConfigParser::T_EQUAL,
                    'EQUAL',
                    '=',
                    new SourceRange(null, $offset, $offset + 1, $line, $column, $line, $column + 1),
                );
                $offset++;
                $column++;
                continue;
            }

            throw new \RuntimeException('Unexpected config character "' . $char . '" at line ' . $line . ', column ' . $column . '.');
        }

        if ($tokens !== [] && $tokens[array_key_last($tokens)]->id() !== ConfigParser::T_NEWLINE) {
            $tokens[] = new Token(
                ConfigParser::T_NEWLINE,
                'NEWLINE',
                "\n",
                new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
            );
        }

        $tokens[] = new Token(
            ConfigParser::T_EOF,
            'EOF',
            null,
            new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
        );

        return $tokens;
    }

    /**
     * @return array{string, int, int}
     */
    private function readSection(int $offset, int $column): array
    {
        $end = strpos($this->source, ']', $offset + 1);
        if ($end === false) {
            throw new \RuntimeException('Unterminated config section at column ' . $column . '.');
        }

        $name = trim(substr($this->source, $offset + 1, $end - $offset - 1));
        if ($name === '') {
            throw new \RuntimeException('Empty config section at column ' . $column . '.');
        }

        return [$name, $end + 1, $column + $end - $offset + 1];
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

            if ($char === "\n") {
                throw new \RuntimeException('Unterminated config string at column ' . $column . '.');
            }

            $value .= $char;
            $end++;
        }

        throw new \RuntimeException('Unterminated config string at column ' . $column . '.');
    }

    /**
     * @return array{int|float, int, int}
     */
    private function readNumber(int $offset, int $column): array
    {
        if (preg_match('/-?[0-9]+(?:\.[0-9]+)?/A', substr($this->source, $offset), $matches) !== 1) {
            throw new \RuntimeException('Invalid config number at column ' . $column . '.');
        }

        $raw = $matches[0];
        $value = str_contains($raw, '.') ? (float) $raw : (int) $raw;

        return [$value, $offset + strlen($raw), $column + strlen($raw)];
    }

    /**
     * @return array{int, string, mixed, int, int}
     */
    private function readWord(int $offset, int $column): array
    {
        preg_match('/[A-Za-z_][A-Za-z0-9_.-]*/A', substr($this->source, $offset), $matches);
        $word = $matches[0];
        $lower = strtolower($word);
        $token = match ($lower) {
            'true', 'yes', 'on' => [ConfigParser::T_BOOL, 'BOOL', true],
            'false', 'no', 'off' => [ConfigParser::T_BOOL, 'BOOL', false],
            default => [ConfigParser::T_IDENT, 'IDENT', $word],
        };

        return [$token[0], $token[1], $token[2], $offset + strlen($word), $column + strlen($word)];
    }
}
