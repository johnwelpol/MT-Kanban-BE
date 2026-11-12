<?php

namespace Tests\Unit;

use App\Support\FrontendUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FrontendUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'frontend.root_domain' => 'localhost',
            'frontend.protocol' => 'http',
            'frontend.port' => '3000',
        ]);
    }

    public function test_tenant_url_includes_slug_and_port(): void
    {
        $this->assertSame('http://acme.localhost:3000/login', FrontendUrl::tenant('acme', '/login'));
    }

    public function test_root_url_includes_port(): void
    {
        $this->assertSame('http://localhost:3000/', FrontendUrl::root());
    }

    #[DataProvider('omittedPorts')]
    public function test_port_is_omitted_when_empty_or_default(?string $port): void
    {
        config(['frontend.port' => $port]);

        $this->assertSame('http://acme.localhost/login', FrontendUrl::tenant('acme', '/login'));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function omittedPorts(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'http' => ['80'],
            'https' => ['443'],
        ];
    }
}
