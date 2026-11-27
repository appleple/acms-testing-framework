<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Http;

use Acms\TestingFramework\Http\TestResponse;
use Acms\TestingFramework\TestCase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(TestResponse::class)]
final class TestResponseTest extends TestCase
{
    #[Test]
    #[TestDox('ステータス・ヘッダー・本文を読め、本文は何度読んでも同じ')]
    public function 読み出し(): void
    {
        $response = new TestResponse(new Response(201, ['X-Foo' => ['a', 'b']], 'body'));

        $this->assertSame(201, $response->status());
        $this->assertSame('a, b', $response->header('X-Foo'));
        $this->assertSame('', $response->header('X-Missing'));
        $this->assertSame('body', $response->body());
        $this->assertSame('body', $response->body());
        $this->assertSame(201, $response->psr()->getStatusCode());
    }

    #[Test]
    public function ステータスの検証はメソッドチェーンで書ける(): void
    {
        $response = new TestResponse(new Response(404, ['X-Foo' => 'bar'], 'not found here'));

        $response->assertStatus(404)->assertSee('not found')->assertDontSee('found!')->assertHeader('X-Foo')->assertHeader('X-Foo', 'bar');

        $this->expectException(AssertionFailedError::class);
        $response->assertStatus(200);
    }

    #[Test]
    public function ヘッダーが無ければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200)))->assertHeader('X-Foo');
    }

    #[Test]
    public function ヘッダーの値が違えば失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, ['X-Foo' => 'bar'])))->assertHeader('X-Foo', 'baz');
    }

    #[Test]
    public function 含まれるはずの文字列が無ければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], 'abc')))->assertSee('xyz');
    }

    #[Test]
    public function 含まれないはずの文字列があれば失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], 'abc')))->assertDontSee('b');
    }

    #[Test]
    #[TestDox('assertRedirect は転送のステータスだけを見るか、絶対 URL・パスで転送先まで見る')]
    public function 転送の検証(): void
    {
        $response = new TestResponse(new Response(302, ['Location' => 'http://a.example.test/done/?x=1']), 'a.example.test');

        $response->assertRedirect()
            ->assertRedirect('http://a.example.test/done/?x=1')
            ->assertRedirect('/done/?x=1');
        $this->assertSame(302, $response->status());
    }

    #[Test]
    public function 転送先が違えば失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(302, ['Location' => 'http://a.example.test/done/']), 'a.example.test'))->assertRedirect('/other/');
    }

    #[Test]
    #[TestDox('パスで転送先を確かめるとき、別のホストへの転送は失敗する（絶対 URL で渡せば確かめられる）')]
    public function 別のホストへの転送はパスでは通らない(): void
    {
        $response = new TestResponse(new Response(302, ['Location' => 'https://evil.example.test/done/']), 'a.example.test');

        $response->assertRedirect('https://evil.example.test/done/');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('別のホスト');
        $response->assertRedirect('/done/');
    }

    #[Test]
    #[TestDox('リクエストのホストが分からないときは、絶対 URL の転送先をパスと照合できず失敗する')]
    public function ホストが不明ならパスで照合できない(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(302, ['Location' => 'http://a.example.test/done/'])))->assertRedirect('/done/');
    }

    #[Test]
    #[TestDox('相対パスの Location は、ホストを比べずにパスだけで照合する。ホストは大文字小文字・ポート付きでも同じホストなら通る')]
    public function 相対の転送先とホストの表記(): void
    {
        (new TestResponse(new Response(302, ['Location' => '/done/?x=1'])))->assertRedirect('/done/?x=1');
        (new TestResponse(new Response(302, ['Location' => 'http://A.Example.test:8080/done/']), 'a.example.test:8080'))->assertRedirect('/done/');
    }

    #[Test]
    #[TestDox('同じホストでもポートが違えば別のホストとして失敗する')]
    public function ポートが違えば別のホスト(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(302, ['Location' => 'http://a.example.test:81/done/']), 'a.example.test'))->assertRedirect('/done/');
    }

    #[Test]
    public function 転送でないステータスは失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200)))->assertRedirect();
    }

    #[Test]
    #[TestDox('assertJson は連想配列を部分一致で、リストを完全一致で比べる')]
    public function JSONの検証(): void
    {
        $response = new TestResponse(new Response(200, [], '{"ok":true,"form":{"name":"a","other":1},"items":[1,2]}'));

        $response->assertJson(['ok' => true])->assertJson(['form' => ['name' => 'a']])->assertJson(['items' => [1, 2]]);
        $this->assertSame(['ok' => true, 'form' => ['name' => 'a', 'other' => 1], 'items' => [1, 2]], $response->json());
    }

    #[Test]
    #[TestDox('assertJson([]) は空のリスト（本文が [] か {}）とだけ一致する')]
    public function 空の配列は空のJSONとだけ一致する(): void
    {
        (new TestResponse(new Response(200, [], '[]')))->assertJson([]);
        (new TestResponse(new Response(200, [], '{}')))->assertJson([]);
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '{"ok":true}')))->assertJson([]);
    }

    #[Test]
    #[TestDox('assertOk() は 200、assertForbidden() は 403 だけを通す')]
    public function ステータスの短縮(): void
    {
        (new TestResponse(new Response(200)))->assertOk();
        (new TestResponse(new Response(403)))->assertForbidden();
        try {
            (new TestResponse(new Response(403)))->assertOk();
            $this->fail('403 は assertOk() を通らない');
        } catch (AssertionFailedError) {
            // 期待どおり
        }
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200)))->assertForbidden();
    }

    #[Test]
    public function JSONの値が違えば失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '{"ok":true}')))->assertJson(['ok' => false]);
    }

    #[Test]
    public function JSONにキーが無ければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '{"ok":true}')))->assertJson(['missing' => 1]);
    }

    #[Test]
    public function 入れ子のJSONにキーが無ければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '{"form":{"a":1}}')))->assertJson(['form' => ['b' => 1]]);
    }

    #[Test]
    public function リストが違えば失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '{"items":[1,2]}')))->assertJson(['items' => [1]]);
    }

    #[Test]
    public function 本文がJSONでなければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], 'not json')))->json();
    }

    #[Test]
    public function JSONが配列でなければ失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        (new TestResponse(new Response(200, [], '"text"')))->assertJson(['a' => 1]);
    }
}
