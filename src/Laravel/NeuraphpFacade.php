<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Laravel;

use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\ModelReference;
use B7s\Neuraphp\Neuraphp;
use B7s\Neuraphp\NeuraphpResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Neuraphp model(Model|ModelReference $model)
 * @method static Neuraphp quantization(Quantization $quantization)
 * @method static Neuraphp threads(int $threads)
 * @method static Neuraphp modelPath(string $modelPath)
 * @method static Neuraphp libraryPath(string $libraryPath)
 * @method static Neuraphp configPath(string $configPath)
 * @method static NeuraphpResult embed(string $text)
 * @method static NeuraphpResult[] embedBatch(array $texts)
 * @method static float cosineSimilarity(string $textA, string $textB)
 * @method static int|null dimension()
 * @method static bool isAvailable()
 */
final class NeuraphpFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Neuraphp::class;
    }
}
