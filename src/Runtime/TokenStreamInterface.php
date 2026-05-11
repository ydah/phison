<?php

declare(strict_types=1);

namespace Phison\Runtime;

interface TokenStreamInterface
{
    public function current(): TokenInterface;

    public function advance(): void;

    /**
     * @return list<TokenInterface>
     */
    public function previousTokens(int $count): array;

    /**
     * @return list<TokenInterface>
     */
    public function nextTokens(int $count): array;
}
