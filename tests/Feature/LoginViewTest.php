<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginViewTest extends TestCase
{
    public function test_login_page_uses_premium_auth_layout(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Infraestrutura. Conectividade. Inteligência.')
            ->assertSee('Acessar o IRCENTER')
            ->assertSee('Manter sessão ativa')
            ->assertSee('auth-network-visual', false)
            ->assertSee('auth-theme-toggle', false)
            ->assertSee('password-toggle', false);
    }

    public function test_login_page_has_no_external_assets(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString(
            'https://fonts.googleapis.com',
            $html
        );

        $this->assertStringNotContainsString(
            'cdnjs.cloudflare.com',
            $html
        );

        $this->assertStringNotContainsString(
            'unpkg.com',
            $html
        );
    }
}
