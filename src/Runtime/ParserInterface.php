<?php

declare(strict_types=1);

namespace Phison\Runtime;

interface ParserInterface
{
    public function parse(TokenStreamInterface $tokens, mixed $context = null): mixed;
}
