<?php

/**
 * FFI stub for static analysis and IDE autocompletion.
 *
 * The FFI extension is a PHP core extension available since PHP 7.4.
 * This stub file is provided so that static analysis tools (PHPStan,
 * Psalm, Intelephense, etc.) can resolve FFI types even when the
 * extension is not loaded in the analysis environment.
 *
 * @link https://www.php.net/manual/en/book.ffi.php
 *
 * @method FFI\CData bert_load_from_file(string $fname)
 * @method void bert_free(FFI\CData $ctx)
 * @method int bert_n_embd(FFI\CData $ctx)
 * @method int bert_n_max_tokens(FFI\CData $ctx)
 * @method void bert_encode(FFI\CData $ctx, int $n_threads, string $texts, FFI\CData $embeddings)
 */

namespace FFI {

    /**
     * @link https://www.php.net/manual/en/class.ffi-cdata.php
     */
    class CData {}

    /**
     * @link https://www.php.net/manual/en/class.ffi-exception.php
     */
    class Exception extends \Error {}
}

namespace {
    use FFI\CData;

    /**
     * @link https://www.php.net/manual/en/class.ffi.php
     *
     * @method static mixed __callStatic(string $name, array $args)
     * @method mixed __call(string $name, array $args)
     */
    final class FFI
    {
        /**
         * @link https://www.php.net/manual/en/ffi.cdef.php
         *
         * @return static
         */
        public static function cdef(string $code = '', ?string $lib = null): self
        {
            return new self;
        }

        /**
         * @link https://www.php.net/manual/en/ffi.new.php
         */
        public function new(string $type, bool $owned = true, bool $persistent = false): CData
        {
            return new CData;
        }

        /**
         * @link https://www.php.net/manual/en/ffi.cast.php
         */
        public static function cast(string $type, mixed $ptr): CData
        {
            return new CData;
        }

        /**
         * @link https://www.php.net/manual/en/ffi.type.php
         */
        public static function type(string $type): ?object
        {
            return null;
        }

        /**
         * @link https://www.php.net/manual/en/ffi.free.php
         */
        public static function free(CData $ptr): void {}

        /**
         * @link https://www.php.net/manual/en/ffi.scope.php
         *
         * @return static
         */
        public static function scope(string $name): self
        {
            return new self;
        }

        /**
         * @link https://www.php.net/manual/en/ffi.load.php
         *
         * @return static|null
         */
        public static function load(string $filename): ?self
        {
            return null;
        }

        public function __call(string $name, array $args): mixed
        {
            return null;
        }

        public static function __callStatic(string $name, array $args): mixed
        {
            return null;
        }
    }
}
