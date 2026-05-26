<?php

declare(strict_types=1);

use B7s\Neuraphp\Exceptions\FFIException;
use B7s\Neuraphp\Exceptions\LibraryNotFoundException;
use B7s\Neuraphp\Exceptions\ModelNotFoundException;
use B7s\Neuraphp\Exceptions\NeuraphpException;

describe('NeuraphpException', function () {
    it('extends RuntimeException', function () {
        $exception = new NeuraphpException('test');
        expect($exception)->toBeInstanceOf(RuntimeException::class);
    });
});

describe('LibraryNotFoundException', function () {
    it('creates exception with path', function () {
        $exception = LibraryNotFoundException::withPath('/usr/lib/libbert_shared.so');
        expect($exception->getMessage())->toContain('/usr/lib/libbert_shared.so');
        expect($exception)->toBeInstanceOf(NeuraphpException::class);
    });

    it('creates exception with search paths', function () {
        $exception = LibraryNotFoundException::withSearchPaths(['/usr/lib', '/usr/local/lib']);
        expect($exception->getMessage())->toContain('/usr/lib');
        expect($exception->getMessage())->toContain('/usr/local/lib');
    });
});

describe('ModelNotFoundException', function () {
    it('creates exception with path', function () {
        $exception = ModelNotFoundException::withPath('/path/to/model.bin');
        expect($exception->getMessage())->toContain('/path/to/model.bin');
        expect($exception)->toBeInstanceOf(NeuraphpException::class);
    });

    it('creates exception with model name', function () {
        $exception = ModelNotFoundException::withModel('all-MiniLM-L6-v2');
        expect($exception->getMessage())->toContain('all-MiniLM-L6-v2');
    });
});

describe('FFIException', function () {
    it('creates extension not loaded exception', function () {
        $exception = FFIException::extensionNotLoaded();
        expect($exception->getMessage())->toContain('FFI extension');
        expect($exception)->toBeInstanceOf(NeuraphpException::class);
    });

    it('creates load failed exception', function () {
        $exception = FFIException::loadFailed('libbert_shared.so', 'some error');
        expect($exception->getMessage())->toContain('libbert_shared.so');
        expect($exception->getMessage())->toContain('some error');
    });

    it('creates function not found exception', function () {
        $exception = FFIException::functionNotFound('bert_encode');
        expect($exception->getMessage())->toContain('bert_encode');
    });

    it('creates encoding failed exception', function () {
        $exception = FFIException::encodingFailed('timeout');
        expect($exception->getMessage())->toContain('timeout');
    });
});
