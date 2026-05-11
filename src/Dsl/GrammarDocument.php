<?php

declare(strict_types=1);

namespace Phison\Dsl;

final class GrammarDocument
{
    /**
     * @param list<TokenDefinition> $tokens
     * @param list<PrecedenceDeclaration> $precedences
     * @param list<RuleDefinition> $rules
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $namespace,
        public readonly ?string $parserClass,
        public readonly string $start,
        public readonly array $tokens,
        public readonly array $precedences,
        public readonly array $rules,
    ) {
    }
}
