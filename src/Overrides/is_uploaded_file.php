<?php

/**
 * Namespace trick: 各ドメインクラス内で非修飾で呼ばれる is_uploaded_file() をオーバーライドする。
 * PHP の namespace fallback により、同名前空間の関数が優先される。
 *
 * テスト環境では実際の HTTP アップロードが存在しないため、
 * ファイルが物理的に存在すれば true を返すようにする。
 *
 * 対象:
 * - Acms\Services\Common\Helper（レガシー互換シム。呼び出し先に委譲するだけで自身は呼ばない）
 * - Acms\Services\Field\FieldExtractor（POST → Field 変換の実体。extract() が呼ぶ）
 * - Acms\Services\Validator\FileUploadValidator（$_FILES のエラーコード検証の実体）
 */

namespace Acms\Services\Common;

function is_uploaded_file(string $filename): bool
{
    return file_exists($filename);
}

namespace Acms\Services\Field;

function is_uploaded_file(string $filename): bool
{
    return file_exists($filename);
}

namespace Acms\Services\Validator;

function is_uploaded_file(string $filename): bool
{
    return file_exists($filename);
}
