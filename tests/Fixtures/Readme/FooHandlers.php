<?php

/**
 * README の機能テストの例が呼ぶ、プラグイン Foo の POST モジュールに見立てたハンドラ
 *
 * README の例とテスト（`tests/Integration/Readme/`・`tests/Unit/Readme/`）を食い違わせないため、README に書いた例はすべて、そのままテストとして流している。
 */

use Acms\Services\Facades\Login;
use Acms\Services\Facades\Response;
use Acms\Services\Http\Kernel\CurrentContext;

class ACMS_POST_Foo_Update extends ACMS_POST
{
    public function post()
    {
        if (!Login::isLoggedIn()) {
            die403('forbidden');
        }
        $sql = SQL::newUpdate('blog');
        $sql->addUpdate('blog_name', (string) ($_POST['title'] ?? ''));
        $sql->addWhereOpr('blog_id', (int) CurrentContext::blogId());
        Acms\Services\Facades\Database::query($sql->get(dsn()), 'exec');

        $this->redirect('http://' . $_SERVER['HTTP_HOST'] . '/foo/');
    }
}

class ACMS_POST_Foo_Search extends ACMS_POST
{
    public function post()
    {
        Response::json(['ok' => true, 'items' => ['count' => 1], 'q' => $_POST['q'] ?? null]);
    }
}

class ACMS_POST_Foo_Notify extends ACMS_POST
{
    public function post()
    {
        Acms\Services\Facades\Mailer::init()->setFrom('from@example.test')->setTo('to@example.test')->setSubject('件名')->setBody('本文')->send();

        Response::json(['ok' => true]);
    }
}
