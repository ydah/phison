<?php

declare(strict_types=1);

namespace Example\Csv;

use Example\Csv\Generated\CsvParser;
use Example\Support\ArrayTokenStream;
use Example\Support\Token;
use Phison\Runtime\SourceRange;
use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

final class CsvLexer
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
            [$field, $fieldEndOffset, $fieldEndColumn] = $this->readField($offset, $column);
            $tokens[] = new Token(
                CsvParser::T_FIELD,
                'FIELD',
                $field,
                new SourceRange(null, $offset, $fieldEndOffset, $line, $column, $line, $fieldEndColumn),
            );
            $offset = $fieldEndOffset;
            $column = $fieldEndColumn;

            if ($offset >= $length) {
                break;
            }

            $char = $this->source[$offset];
            if ($char === ',') {
                $tokens[] = new Token(
                    CsvParser::T_COMMA,
                    'COMMA',
                    ',',
                    new SourceRange(null, $offset, $offset + 1, $line, $column, $line, $column + 1),
                );
                $offset++;
                $column++;
                continue;
            }

            if ($char === "\n") {
                $tokens[] = new Token(
                    CsvParser::T_NEWLINE,
                    'NEWLINE',
                    "\n",
                    new SourceRange(null, $offset, $offset + 1, $line, $column, $line + 1, 1),
                );
                $offset++;
                $line++;
                $column = 1;
                continue;
            }

            throw new \RuntimeException('Unexpected CSV character "' . $char . '" at column ' . $column . '.');
        }

        if ($length > 0 && $this->source[$length - 1] === ',') {
            $tokens[] = new Token(
                CsvParser::T_FIELD,
                'FIELD',
                '',
                new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
            );
        }

        $tokens[] = new Token(
            CsvParser::T_EOF,
            'EOF',
            null,
            new SourceRange(null, $offset, $offset, $line, $column, $line, $column),
        );

        return $tokens;
    }

    /**
     * @return array{string, int, int}
     */
    private function readField(int $offset, int $column): array
    {
        if (($this->source[$offset] ?? '') === '"') {
            return $this->readQuotedField($offset, $column);
        }

        $end = $offset;
        $length = strlen($this->source);
        while ($end < $length && $this->source[$end] !== ',' && $this->source[$end] !== "\n") {
            $end++;
        }

        $raw = substr($this->source, $offset, $end - $offset);

        return [rtrim($raw, "\r"), $end, $column + strlen($raw)];
    }

    /**
     * @return array{string, int, int}
     */
    private function readQuotedField(int $offset, int $column): array
    {
        $end = $offset + 1;
        $length = strlen($this->source);
        $value = '';

        while ($end < $length) {
            $char = $this->source[$end];
            if ($char === '"' && ($this->source[$end + 1] ?? null) === '"') {
                $value .= '"';
                $end += 2;
                continue;
            }

            if ($char === '"') {
                $end++;
                if (($this->source[$end] ?? null) !== ',' && ($this->source[$end] ?? null) !== "\n" && $end < $length) {
                    throw new \RuntimeException('Unexpected text after quoted field at column ' . ($column + $end - $offset) . '.');
                }

                return [$value, $end, $column + $end - $offset];
            }

            if ($char === "\n") {
                throw new \RuntimeException('Quoted multiline CSV fields are not supported in this example.');
            }

            $value .= $char;
            $end++;
        }

        throw new \RuntimeException('Unterminated quoted CSV field at column ' . $column . '.');
    }
}
