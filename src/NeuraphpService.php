<?php

declare(strict_types=1);

namespace B7s\Neuraphp;

use B7s\Neuraphp\Exceptions\FFIException;
use B7s\Neuraphp\Exceptions\LibraryNotFoundException;
use B7s\Neuraphp\Exceptions\ModelNotFoundException;
use Exception;
use FFI\CData;
use InvalidArgumentException;
use Throwable;

final class NeuraphpService
{
    private const string FFI_HEADER = <<<'C'
        typedef struct bert_ctx bert_ctx;
        bert_ctx* bert_load_from_file(const char* fname);
        void bert_free(bert_ctx* ctx);
        int32_t bert_n_embd(bert_ctx* ctx);
        int32_t bert_n_max_tokens(bert_ctx* ctx);
        void bert_encode(bert_ctx* ctx, int32_t n_threads, const char* texts, float* embeddings);
        void bert_free_float(float* ptr);
    C;

    /** @var object|null FFI\CData for bert context */
    private ?object $context = null;

    private ?\FFI $ffi = null;

    private readonly string $libraryPath;

    private readonly string $modelPath;

    private readonly int $threads;

    private bool $initialized = false;

    public function __construct(
        string $libraryPath,
        string $modelPath,
        int $threads = 4,
    ) {
        if (! extension_loaded('ffi')) {
            throw FFIException::extensionNotLoaded();
        }

        $this->libraryPath = $libraryPath;
        $this->modelPath = $modelPath;
        $this->threads = $threads;
    }

    /**
     * Create a NeuraphpService from a Config object.
     */
    public static function fromConfig(Config $config): self
    {
        return new self(
            libraryPath: $config->resolveLibraryPath(),
            modelPath: $config->resolveModelPath(),
            threads: $config->threads(),
        );
    }

    /**
     * Check if the FFI extension and library are available without initializing.
     */
    public function isAvailable(): bool
    {
        if (! extension_loaded('ffi')) {
            return false;
        }

        if (! file_exists($this->libraryPath)) {
            return false;
        }

        if (! file_exists($this->modelPath)) {
            return false;
        }

        return true;
    }

    /**
     * Initialize the FFI bridge and load the model.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        if (! file_exists($this->libraryPath)) {
            throw LibraryNotFoundException::withPath($this->libraryPath);
        }

        if (! file_exists($this->modelPath)) {
            throw ModelNotFoundException::withPath($this->modelPath);
        }

        try {
            $ffi = \FFI::cdef(self::FFI_HEADER, $this->libraryPath);
            $this->ffi = $ffi;

            $context = $ffi->bert_load_from_file($this->modelPath);

            if ($context === null) {
                throw FFIException::loadFailed($this->libraryPath, 'bert_load_from_file returned null');
            }

            $this->context = $context;
            $this->initialized = true;
        } catch (Exception $e) {
            throw FFIException::loadFailed($this->libraryPath, $e->getMessage());
        }
    }

    /**
     * Encode a single text string into an embedding vector.
     *
     * @return float[]
     */
    public function encode(string $text): array
    {
        $this->initialize();

        if ($text === '') {
            throw new InvalidArgumentException('Text to encode must not be empty.');
        }

        $dimensions = $this->getDimensions();
        $ffi = $this->ffi;
        $embeddings = $ffi->new("float[{$dimensions}]");

        $ffi->bert_encode($this->context, $this->threads, $text, $embeddings);

        return $this->extractEmbeddings($embeddings, $dimensions);
    }

    /**
     * Encode multiple texts into embedding vectors.
     *
     * @param  string[]  $texts
     * @return array<float[]>
     */
    public function encodeBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        foreach ($texts as $i => $text) {
            if ($text === '') {
                throw new InvalidArgumentException("Text at index {$i} must not be empty.");
            }
        }

        // Fallback to sequential encoding if batch is not available
        return array_map(fn (string $text): array => $this->encode($text), $texts);
    }

    /**
     * Get the number of embedding dimensions from the loaded model.
     */
    public function getDimensions(): int
    {
        $this->initialize();

        $ffi = $this->ffi;

        return (int) $ffi->bert_n_embd($this->context);
    }

    /**
     * Get the maximum number of tokens from the loaded model.
     */
    public function getMaxTokens(): int
    {
        $this->initialize();

        $ffi = $this->ffi;

        return (int) $ffi->bert_n_max_tokens($this->context);
    }

    /**
     * Get the model file path.
     */
    public function modelPath(): string
    {
        return $this->modelPath;
    }

    /**
     * Get the library file path.
     */
    public function libraryPath(): string
    {
        return $this->libraryPath;
    }

    /**
     * Free the bert context and release resources.
     */
    public function __destruct()
    {
        if ($this->initialized && $this->context !== null && $this->ffi !== null) {
            try {
                $this->ffi->bert_free($this->context);
            } catch (Throwable) {}
        }
    }

    /**
     * Extract embedding values from an FFI float array into a PHP array.
     *
     * @param  CData  $embeddings
     * @return float[]
     */
    private function extractEmbeddings(mixed $embeddings, int $dimensions): array
    {
        $result = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $result[] = (float) $embeddings[$i];
        }

        return $result;
    }
}
