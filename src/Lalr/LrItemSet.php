<?php

declare(strict_types=1);

namespace Phison\Lalr;

use Phison\Grammar\Symbol;

final class LrItemSet
{
    /**
     * @param list<LrItem> $items
     * @param array<string, int> $transitions
     */
    public function __construct(
        public readonly int $id,
        public readonly array $items,
        public readonly array $transitions = [],
    ) {
    }

    public static function symbolKey(Symbol $symbol): string
    {
        return ($symbol->isTerminal() ? 't:' : 'n:') . $symbol->id;
    }

    public function transitionFor(Symbol $symbol): ?int
    {
        return $this->transitions[self::symbolKey($symbol)] ?? null;
    }

    public function identityKey(): string
    {
        $keys = array_map(static fn (LrItem $item): string => $item->key(), $this->items);
        sort($keys, SORT_STRING);

        return implode('|', $keys);
    }

    public function coreKey(): string
    {
        $keys = [];
        foreach ($this->items as $item) {
            $keys[$item->coreKey()] = true;
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return implode('|', $keys);
    }
}
