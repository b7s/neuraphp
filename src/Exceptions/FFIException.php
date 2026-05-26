<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Exceptions;

class FFIException extends NeuraphpException
{
    public static function extensionNotLoaded(): self
    {
        return new self('The FFI extension is not loaded. Please install and enable the PHP FFI extension.');
    }

    public static function loadFailed(string $library, string $error): self
    {
        return new self("Failed to load library '{$library}': {$error}");
    }

    public static function functionNotFound(string $function): self
    {
        return new self("FFI function not found: {$function}");
    }

    public static function encodingFailed(string $reason): self
    {
        return new self("Encoding failed: {$reason}");
    }
}
