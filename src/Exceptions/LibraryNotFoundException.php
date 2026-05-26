<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Exceptions;

class LibraryNotFoundException extends NeuraphpException
{
    public static function withPath(string $path): self
    {
        return new self("Could not find libbert_shared.so at path: {$path}");
    }

    /**
     * @param  array<string>  $paths
     */
    public static function withSearchPaths(array $paths): self
    {
        $searched = implode(', ', $paths);

        return new self("Could not find libbert_shared.so. Searched: {$searched}");
    }
}
