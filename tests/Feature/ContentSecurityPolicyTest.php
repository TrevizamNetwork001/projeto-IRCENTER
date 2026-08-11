<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_is_restrictive_and_enforced(): void
    {
        $response = $this->get(route('login'));
        $policy = $response->headers->get(
            'Content-Security-Policy'
        );

        $response->assertOk()
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');

        $this->assertNotNull($policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("style-src-attr 'none'", $policy);
        $this->assertStringNotContainsString(
            "style-src-attr 'unsafe-inline'",
            $policy
        );
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:^|[;\s])\*(?:[;\s]|$)/',
            $policy
        );
    }

    public function test_nonce_changes_and_matches_every_inline_element(): void
    {
        $first = $this->get(route('login'));
        $second = $this->get(route('login'));

        $firstNonce = $this->nonceFromPolicy($first->headers->get(
            'Content-Security-Policy'
        ));
        $secondNonce = $this->nonceFromPolicy($second->headers->get(
            'Content-Security-Policy'
        ));

        $this->assertNotSame($firstNonce, $secondNonce);
        $this->assertInlineElementsUseNonce(
            $first->getContent(),
            $firstNonce
        );
    }

    public function test_authenticated_layout_uses_header_nonce(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $nonce = $this->nonceFromPolicy($response->headers->get(
            'Content-Security-Policy'
        ));

        $response->assertOk();
        $this->assertInlineElementsUseNonce($response->getContent(), $nonce);
        $this->assertDoesNotMatchRegularExpression(
            '/\sstyle\s*=/i',
            $response->getContent()
        );
    }

    public function test_health_and_error_responses_keep_security_headers(): void
    {
        foreach (['/up', '/endereco-inexistente-h5'] as $uri) {
            $response = $this->get($uri);

            $response
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Content-Security-Policy')
                ->assertHeaderMissing('Content-Security-Policy-Report-Only');
        }
    }

    private function nonceFromPolicy(?string $policy): string
    {
        $this->assertNotNull($policy);
        $matched = preg_match("/'nonce-([^']+)'/", $policy, $matches);

        $this->assertSame(1, $matched);

        return $matches[1];
    }

    private function assertInlineElementsUseNonce(
        string $html,
        string $expectedNonce
    ): void {
        preg_match_all(
            '/<(?:script|style)\b([^>]*)>/i',
            $html,
            $elements
        );

        $this->assertNotEmpty($elements[1]);

        foreach ($elements[1] as $attributes) {
            $this->assertMatchesRegularExpression(
                '/\bnonce="'.preg_quote($expectedNonce, '/').'"/',
                $attributes
            );
        }
    }
}
