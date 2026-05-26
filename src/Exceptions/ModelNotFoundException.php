<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Exceptions;

class ModelNotFoundException extends NeuraphpException
{
    public static function withPath(string $path): self
    {
        return new self("Could not find model file at path: {$path}");
    }

    public static function withModel(string $model): self
    {
        return new self("Could not find model file for model: {$model}");
    }
}
