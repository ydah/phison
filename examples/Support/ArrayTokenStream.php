<?php

declare(strict_types=1);

namespace Example\Support;

use Phison\Runtime\TokenInterface;
use Phison\Runtime\TokenStreamInterface;

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
