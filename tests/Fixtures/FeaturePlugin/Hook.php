<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Fixtures\FeaturePlugin;

use Acms\Services\Common\InjectTemplate;
use Acms\Services\Facades\Application;
use Acms\Services\Http\Context;

/**
 * プラグインの Hook に見立てた部品。描画した本文の末尾に、印と、公開の読み口（`Context`）で読んだ文脈の値を足す
 *
 * `BID` などの定数ではなく `Context` で読むと、機能テストでも、リクエストごとに正しいブログの値になる。
 * `inject` は、`ServiceProvider::init()` が `InjectTemplate` へ足した登録の数（リクエストをまたいで積み上がらないことを確かめる）
 */
final class Hook
{
    /**
     * `ablogcms/extension/acms/Hook.php` の雛形が示す書き方（文脈が確定した後の Hook で `Context` を読む）と同じ読み方をした結果
     *
     * @var array{blogId: mixed, view: mixed, entryDetermined: bool, entryId: mixed}|null
     */
    public static ?array $beforeGetFire = null;

    /**
     * @param string $tpl
     * @param \ACMS_GET $thisModule
     */
    public function beforeGetFire(&$tpl, $thisModule): void
    {
        self::$beforeGetFire = [
            'blogId' => Context::blogId(),
            'view' => Context::view(),
            'entryDetermined' => Context::isEntryIdDetermined(),
            'entryId' => Context::isEntryIdDetermined() ? Context::entryId() : 'undetermined',
        ];
    }

    /**
     * @param string $contents
     */
    public function afterBuild(&$contents): void
    {
        $contents .= '<!-- feature-plugin-hook blog=' . Context::blogId() . ' view=' . Context::view()
            . ' inject=' . count(InjectTemplate::singleton()->get(ServiceProvider::INJECT_TYPE)) . ' md=' . Application::make('markdown')->convert('x') . ' -->';
    }
}
