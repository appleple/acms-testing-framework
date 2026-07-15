<?php

/**
 * Namespace trick: Helper.php (namespace Acms\Services\Common) 内で
 * 非修飾で呼ばれる is_uploaded_file() をオーバーライドする。
 * PHP の namespace fallback により、同名前空間の関数が優先される。
 *
 * テスト環境では実際の HTTP アップロードが存在しないため、
 * ファイルが物理的に存在すれば true を返すようにする。
 */

namespace Acms\Services\Common;

function is_uploaded_file(string $filename): bool
{
    return file_exists($filename);
}
