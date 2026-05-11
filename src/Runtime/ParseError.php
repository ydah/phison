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
        parent::__construct($message ?? self::buildMessage($actual, $expected, $previous, $next));
    }

    /**
     * @param list<string> $expected
     * @param list<TokenInterface> $previous
     * @param list<TokenInterface> $next
     */
    private static function buildMessage(TokenInterface $actual, array $expected, array $previous, array $next): string
    {
        $location = $actual->location();
        $place = $location->file !== null
            ? $location->file . ':' . $location->startLine . ':' . $location->startColumn
            : $location->startLine . ':' . $location->startColumn;

        $expectedText = $expected === []
            ? 'no valid token'
            : implode(', ', $expected);

        $lines = [
            sprintf(
                'Parse error at %s. Unexpected token %s; expected %s.',
                $place,
                self::formatToken($actual),
                $expectedText,
            ),
        ];

        if ($previous !== []) {
            $lines[] = 'Previous tokens: ' . self::formatTokenList($previous);
        }

        if ($next !== []) {
            $lines[] = 'Next tokens: ' . self::formatTokenList($next);
        }

        return implode("\n", $lines);
    }

    private static function formatToken(TokenInterface $token): string
    {
        $value = $token->value();
        if ($value === null) {
            return $token->name();
        }

        if (is_scalar($value)) {
            return sprintf('%s(%s)', $token->name(), json_encode((string) $value, JSON_THROW_ON_ERROR));
        }

        return sprintf(
            '%s(%s)',
            $token->name(),
            get_debug_type($value),
        );
    }

    /**
     * @param list<TokenInterface> $tokens
     */
    private static function formatTokenList(array $tokens): string
    {
        return implode(', ', array_map(
            static fn (TokenInterface $token): string => self::formatToken($token),
            $tokens,
        ));
    }
}
