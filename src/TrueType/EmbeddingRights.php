<?php

declare(strict_types=1);

namespace PDF\Fonts\TrueType;

/** Versioned OS/2 declarations; the original fsType value remains unchanged. */
final readonly class EmbeddingRights
{
    public function __construct(public int $value, public int $version)
    {
        if ($value < 0 || $value > 65535 || $version < 0 || $version > 5) {
            throw new \InvalidArgumentException('Invalid OS/2 embedding declaration.');
        }
        $effective = $version < 2 ? $value & 15 : $value;
        if (($effective & ~0x030E) !== 0 || ($version >= 3 && !in_array($effective & 15, [0, 2, 4, 8], true))) {
            throw new \InvalidArgumentException('Invalid embedding permissions for this OS/2 version.');
        }
    }

    public function permitsOutlineEmbedding(): bool
    {
        if ($this->version >= 2 && ($this->value & 0x0200) !== 0) {
            return false;
        }
        // In versions 0–2 the least restrictive declared permission takes precedence.
        return ($this->value & 12) !== 0 || ($this->value & 2) === 0;
    }
}
