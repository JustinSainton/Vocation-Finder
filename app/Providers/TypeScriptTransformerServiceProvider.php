<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

/**
 * `php artisan typescript:transform` writes every Data object in `app/Data`
 * to `resources/js/types/generated.ts`, which the web app and the Expo app
 * both import. The file is committed; regenerate it whenever a Data object
 * changes.
 */
class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformDirectories(app_path('Data'))
            ->outputDirectory(resource_path('js/types'))
            ->writer(new FlatModuleWriter('generated.ts'))
            ->withoutManifest();
    }
}
