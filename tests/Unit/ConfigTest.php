<?php

declare(strict_types=1);

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\PoolingMode;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Exceptions\LibraryNotFoundException;

describe('Config', function () {
    it('creates with sensible defaults', function () {
        $config = new Config;

        expect($config->model())->toBeNull();
        expect($config->quantization())->toBe(Quantization::Q4_0);
        expect($config->threads())->toBe(4);
        expect($config->poolingMode())->toBe(PoolingMode::Mean);
        expect($config->modelPath())->toBeNull();
        expect($config->libraryPath())->toBeNull();
    });

    it('returns immutable copies when using with* methods', function () {
        $original = new Config;

        $withModel = $original->withModel(Model::AllMiniLML6V2);
        expect($original->model())->toBeNull();
        expect($withModel->model())->toBe(Model::AllMiniLML6V2);

        $withQuantization = $original->withQuantization(Quantization::F16);
        expect($original->quantization())->toBe(Quantization::Q4_0);
        expect($withQuantization->quantization())->toBe(Quantization::F16);

        $withThreads = $original->withThreads(8);
        expect($original->threads())->toBe(4);
        expect($withThreads->threads())->toBe(8);
    });

    it('throws on invalid thread count', function () {
        $config = new Config;
        $config->withThreads(0);
    })->throws(InvalidArgumentException::class, 'Thread count must be at least 1.');

    it('resolves model path with default model when none set', function () {
        $config = new Config;
        $path = $config->resolveModelPath();

        expect($path)->toContain('all-MiniLM-L6-v2');
        expect($path)->toContain('ggml-model-q4_0.bin');
    });

    it('resolves model path with explicit model', function () {
        $config = (new Config)->withModel(Model::BgeBaseENV15);
        $path = $config->resolveModelPath();

        expect($path)->toContain('bge-base-en-v1.5');
    });

    it('resolves model path with explicit override', function () {
        $config = (new Config)->withModelPath('/custom/path/model.bin');
        $path = $config->resolveModelPath();

        expect($path)->toBe('/custom/path/model.bin');
    });

    it('throws when library not found', function () {
        $config = new Config;
        $config->resolveLibraryPath();
    })->throws(LibraryNotFoundException::class);

    it('resolves library path with explicit override', function () {
        $tmpFile = sys_get_temp_dir().'/test_libbert_shared.so';
        file_put_contents($tmpFile, 'test');

        try {
            $config = (new Config)->withLibraryPath($tmpFile);
            expect($config->resolveLibraryPath())->toBe($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    });

    it('throws when explicit library path does not exist', function () {
        $config = (new Config)->withLibraryPath('/nonexistent/libbert_shared.so');
        $config->resolveLibraryPath();
    })->throws(LibraryNotFoundException::class);

    it('resolves config from file', function () {
        $tmpFile = sys_get_temp_dir().'/test_neuraphp_config.php';
        file_put_contents($tmpFile, '<?php return ["threads" => 8, "quantization" => "f16"];');

        try {
            $config = Config::resolve($tmpFile);
            expect($config->threads())->toBe(8);
            expect($config->quantization())->toBe(Quantization::F16);
        } finally {
            @unlink($tmpFile);
        }
    });
});
