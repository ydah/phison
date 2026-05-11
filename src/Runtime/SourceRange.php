<?php

declare(strict_types=1);

namespace Phison\Runtime;

final class SourceRange
{
    public function __construct(
        public readonly ?string $file,
        public readonly int $startOffset,
        public readonly int $endOffset,
        public readonly int $startLine,
        public readonly int $startColumn,
        public readonly int $endLine,
        public readonly int $endColumn,
    ) {}

    public static function unknown(): self
    {
        return new self(null, 0, 0, 1, 1, 1, 1);
    }

    public static function merge(?self $first, ?self $last): self
    {
        if ($first === null && $last === null) {
            return self::unknown();
        }

        if ($first === null) {
            return $last;
        }

        if ($last === null) {
            return $first;
        }

        return new self(
            $first->file ?? $last->file,
            $first->startOffset,
            $last->endOffset,
            $first->startLine,
            $first->startColumn,
            $last->endLine,
            $last->endColumn,
        );
    }
}
