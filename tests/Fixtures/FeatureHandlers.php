<?php

/**
 * FeatureTestCase のテストが POST で呼ぶ、プラグインの POST モジュールに見立てたハンドラ
 *
 * プラグインの POST モジュールと同じく、グローバル名前空間の `ACMS_POST_*` クラスとして置く（`ACMS/POST/` の規約でクラスが見つかる）。
 */

use Acms\Services\Facades\Response;

class ACMS_POST_FeatureFixture_Json extends ACMS_POST
{
    public function post()
    {
        Response::json([
            'ok' => true,
            'form' => ['name' => $_POST['name'] ?? null],
            'header' => $_SERVER['HTTP_X_FEATURE'] ?? null,
            'cookie' => $_COOKIE['feature_cookie'] ?? null,
        ]);
    }
}

class ACMS_POST_FeatureFixture_Forbidden extends ACMS_POST
{
    public function post()
    {
        die403('plugin forbidden');
    }
}

class ACMS_POST_FeatureFixture_Redirect extends ACMS_POST
{
    public function post()
    {
        $this->redirect('http://' . $_SERVER['HTTP_HOST'] . '/done/');
    }
}

class ACMS_POST_FeatureFixture_ExternalRedirect extends ACMS_POST
{
    public function post()
    {
        $this->redirect('https://external.example.test/done/');
    }
}

/**
 * 書き込み系のハンドラに見立てた、ブログ名を更新するハンドラ（副作用は DB の行）
 */
class ACMS_POST_FeatureFixture_Save extends ACMS_POST
{
    public function post()
    {
        $sql = SQL::newUpdate('blog');
        $sql->addUpdate('blog_name', (string) ($_POST['name'] ?? ''));
        $sql->addWhereOpr('blog_id', (int) ($_POST['blog_id'] ?? 0));
        Acms\Services\Facades\Database::query($sql->get(dsn()), 'exec');
        Response::json(['saved' => true]);
    }
}
