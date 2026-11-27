<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Fixtures\FeaturePlugin;

use Acms\Services\Http\Context;
use Acms\Services\Http\Kernel\UndeterminedContextError;

/**
 * `ablogcms/extension/acms/Hook.php` の `init()` に見立てた部品。`init` の時点で文脈がまだ確定していないことを記録する
 *
 * `init` は、ブログの解決（`blog.resolve`）・表示型の解決（`view.resolve`）より前に発火する。`Context::isBlogIdDetermined()` は false で、
 * `Context::blogId()` / `view()` を読むと `UndeterminedContextError` になる。雛形の `Hook.php` が「init() では文脈を読まず、
 * 確定した後の Hook で読む」と書く根拠を、本番と同じ発火位置で確かめる。
 */
final class InitHook
{
    /**
     * @var array{blogDetermined: bool, blogIdThrows: bool, viewThrows: bool}|null
     */
    public static ?array $observed = null;

    public function init(): void
    {
        self::$observed = [
            'blogDetermined' => Context::isBlogIdDetermined(),
            'blogIdThrows' => self::throwsUndetermined(static fn () => Context::blogId()),
            'viewThrows' => self::throwsUndetermined(static fn () => Context::view()),
        ];
    }

    private static function throwsUndetermined(\Closure $read): bool
    {
        try {
            $read();
        } catch (UndeterminedContextError) {
            return true;
        }

        return false;
    }
}
