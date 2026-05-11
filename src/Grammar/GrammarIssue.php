<?php

declare(strict_types=1);

namespace Phison\Grammar;

final class GrammarIssue
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    public function __construct(
        public readonly string $severity,
        public readonly string $message,
    ) {}
}
