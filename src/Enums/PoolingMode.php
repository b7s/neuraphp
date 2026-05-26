<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Enums;

enum PoolingMode: string
{
    case Mean = 'mean';
    case CLS = 'cls';
    case Last = 'last';

    /**
     * Get a human-readable description of this pooling strategy.
     */
    public function description(): string
    {
        return match ($this) {
            self::Mean => 'Average all token embeddings',
            self::CLS => 'Use the [CLS] token embedding',
            self::Last => 'Use the last token embedding',
        };
    }
}
