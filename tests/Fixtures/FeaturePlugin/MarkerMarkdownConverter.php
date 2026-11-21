<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Fixtures\FeaturePlugin;

use Acms\Services\Markdown\Contracts\Converter;

/**
 * `ServiceProvider::init()` が `Container::override('markdown', ...)` で差し替える変換器。差し替えが残っていないかを、出力の印で見分ける
 */
final class MarkerMarkdownConverter implements Converter
{
    public function convert(string $markdown): string
    {
        return 'feature-fixture-markdown';
    }
}
