<?php

declare(strict_types=1);

namespace Phison\Runtime;

final class ParseError extends \RuntimeException
{
    /**
     * @param list<string> $expected
     * @param list<TokenInterface> $previous
     * @param list<TokenInterface> $next
     */
    public function __construct(
        public readonly TokenInterface $actual,
        public readonly array $expected,
        public readonly array $previous = [],
        public readonly array $next = [],
        ?string $message = null,
    ) {
        parent::__construct($message ?? self::buildMessage($actual, $expected));
    }

    /**
     * @param list<string> $expected
     */
    private static function buildMessage(TokenInterface $actual, array $expected): string
    {
        $location = $actual->location();
        $place = $location->file !== null
            ? $location->file . ':' . $location->startLine . ':' . $location->startColumn
            : $location->startLine . ':' . $location->startColumn;

        $expectedText = $expected === []
            ? 'no valid token'
            : implode(', ', $expected);

        return sprintf(
            'Parse error at %s. Unexpected token %s; expected %s.',
            $place,
            $actual->name(),
            $expectedText,
        );
    }
}
