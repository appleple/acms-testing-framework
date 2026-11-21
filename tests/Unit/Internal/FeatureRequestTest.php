<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Internal;

use Acms\TestingFramework\Internal\FeatureRequest;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(FeatureRequest::class)]
final class FeatureRequestTest extends TestCase
{
    #[Test]
    #[TestDox('URL からスキーム・ホスト・パス・クエリ文字列を取り出す')]
    public function URLを分解する(): void
    {
        $request = FeatureRequest::get('https://Example.test:8443/news/?page=2');

        $this->assertSame('GET', $request->method);
        $this->assertTrue($request->isHttps());
        $this->assertSame('Example.test:8443', $request->host);
        $this->assertSame('example.test', $request->hostName());
        $this->assertSame('/news/?page=2', $request->requestUri());
        $this->assertSame('/news', $request->requestPath());
        $this->assertSame('https://Example.test:8443/news/?page=2', $request->requestUrl());
    }

    #[Test]
    #[TestDox('パスが無い URL は / として扱い、スキームが無ければ http にする')]
    public function パスとスキームの既定値(): void
    {
        $request = FeatureRequest::get('//example.test');

        $this->assertFalse($request->isHttps());
        $this->assertSame('/', $request->requestUri());
        $this->assertSame('', $request->requestPath());
    }

    #[Test]
    #[TestDox('ホストを含まない URL は拒否する')]
    public function ホストが無ければ拒否する(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FeatureRequest::get('/news/');
    }

    #[Test]
    #[TestDox('POST は本文を持ち、元のリクエストは変わらない（不変）')]
    public function 派生は元を変えない(): void
    {
        $base = FeatureRequest::post('http://example.test/form/', ['name' => 'a']);

        $withAuth = $base->withBasicAuth('alice', 'secret')->withCookies(['sid' => '1'])->withServer(['HTTP_USER_AGENT' => 'ua']);

        $this->assertSame('POST', $withAuth->method);
        $this->assertSame(['name' => 'a'], $withAuth->post);
        $this->assertSame(['HTTP_USER_AGENT' => 'ua', 'PHP_AUTH_USER' => 'alice', 'PHP_AUTH_PW' => 'secret'], $withAuth->server);
        $this->assertSame(['sid' => '1'], $withAuth->cookies);
        $this->assertSame([], $base->server);
        $this->assertSame([], $base->cookies);
    }
}
