<?php

declare(strict_types=1);

namespace Phison\CodeGen;

final class PhpTargetProfile
{
    public function __construct(
        public readonly string $version,
        public readonly bool $supportsReadonlyClass,
        public readonly bool $supportsEnum,
        public readonly bool $supportsMatch,
        public readonly bool $supportsTypedConstants,
    ) {}

    public static function forVersion(string $version): self
    {
        return match ($version) {
            '8.2' => new self($version, true, true, true, false),
            '8.3' => new self($version, true, true, true, true),
            '8.4', '8.5' => new self($version, true, true, true, true),
            default => throw new \InvalidArgumentException('Unsupported PHP target: ' . $version),
        };
    }
}
