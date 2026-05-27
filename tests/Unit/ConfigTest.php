<?php

declare(strict_types=1);

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\PoolingMode;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Exceptions\LibraryNotFoundException;
use B7s\Neuraphp\ModelReference;

describe('Config', function () {
    it('creates with sensible defaults', function () {
        $config = new Config;

        expect($config->model())->toBeNull()
            ->and($config->quantization())->toBe(Quantization::default())
            ->and($config->threads())->toBe(4)
            ->and($config->poolingMode())->toBe(PoolingMode::Mean)
            ->and($config->modelPath())->toBeNull()
            ->and($config->libraryPath())->toBeNull();
    });

    it('returns immutable copies when using with* methods', function () {
        $original = new Config;

        $withModel = $original->withModel(ModelReference::fromEnum(Model::default()));
        expect($original->model())->toBeNull()
            ->and($withModel->model()->huggingFaceId())->toBe('sentence-transformers/all-MiniLM-L6-v2');

        $withQuantization = $original->withQuantization(Quantization::F16);
        expect($original->quantization())->toBe(Quantization::default())
            ->and($withQuantization->quantization())->toBe(Quantization::F16);

        $withThreads = $original->withThreads(8);
        expect($original->threads())->toBe(4)
            ->and($withThreads->threads())->toBe(8);
    });

    it('throws on invalid thread count', function () {
        $config = new Config;
        $config->withThreads(0);
    })->throws(InvalidArgumentException::class, 'Thread count must be at least 1.');

    it('resolves model path with default model when none set', function () {
        $config = new Config;
        $path = $config->resolveModelPath();

        expect($path)->toContain('all-MiniLM-L6-v2')
            ->and($path)->toContain('ggml-model-q4_0.bin');
    });

    it('resolves model path with explicit known model', function () {
        $config = (new Config)->withModel(ModelReference::fromEnum(Model::BgeBaseENV15));
        $path = $config->resolveModelPath();

        expect($path)->toContain('bge-base-en-v1.5');
    });

    it('resolves model path with custom model via ModelReference', function () {
        $config = (new Config)->withModel(ModelReference::fromId('custom-org/my-model'));
        $path = $config->resolveModelPath();

        expect($path)->toContain('my-model')
            ->and($path)->toContain('ggml-model-q4_0.bin');
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
            expect($config->threads())->toBe(8)
                ->and($config->quantization())->toBe(Quantization::F16);
        } finally {
            @unlink($tmpFile);
        }
    });

    it('resolves custom model from config file with dimensions', function () {
        $tmpFile = sys_get_temp_dir().'/test_neuraphp_custom_model.php';
        file_put_contents($tmpFile, '<?php return ["model" => "custom-org/my-model", "model_dimensions" => 768, "model_max_tokens" => 512];');

        try {
            $config = Config::resolve($tmpFile);
            $model = $config->model();

            expect($model)->not->toBeNull()
                ->and($model->huggingFaceId())->toBe('custom-org/my-model')
                ->and($model->dimensions())->toBe(768)
                ->and($model->maxTokens())->toBe(512)
                ->and($model->isKnown())->toBeFalse();
        } finally {
            @unlink($tmpFile);
        }
    });

    it('resolves known model from config file via full HuggingFace ID', function () {
        $tmpFile = sys_get_temp_dir().'/test_neuraphp_hf_id.php';
        file_put_contents($tmpFile, '<?php return ["model" => "BAAI/bge-large-en-v1.5"];');

        try {
            $config = Config::resolve($tmpFile);
            $model = $config->model();

            expect($model)->not->toBeNull()
                ->and($model->huggingFaceId())->toBe('BAAI/bge-large-en-v1.5')
                ->and($model->dimensions())->toBe(1024)
                ->and($model->isKnown())->toBeTrue();
        } finally {
            @unlink($tmpFile);
        }
    });
});
