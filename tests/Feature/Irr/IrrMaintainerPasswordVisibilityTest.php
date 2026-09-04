<?php

namespace Tests\Feature\Irr;

use App\Models\IrrMaintainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrrMaintainerPasswordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function maintainer(): IrrMaintainer
    {
        return IrrMaintainer::query()->create([
            'asn' => 64500,
            'mntner' => 'MAINT-AS64500',
            'password' => 'segredo-do-mntner',
            'admin_c' => 'JD1-TC',
            'tech_c' => 'JD1-TC',
        ]);
    }

    public function test_password_is_absent_from_to_array(): void
    {
        $maintainer = $this->maintainer();

        $array = $maintainer->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_password_is_absent_from_to_json_and_json_serialize(): void
    {
        $maintainer = $this->maintainer();

        $decoded = json_decode($maintainer->toJson(), true);
        $this->assertArrayNotHasKey('password', $decoded);

        $decodedViaEncode = json_decode(json_encode($maintainer), true);
        $this->assertArrayNotHasKey('password', $decodedViaEncode);
    }

    public function test_password_is_absent_when_model_is_embedded_in_a_json_response(): void
    {
        // Nenhuma rota hoje devolve o model diretamente como JSON, mas o
        // teste garante que, se uma vier a existir, o comportamento
        // padrão do Laravel (response()->json($model)) já nasce seguro.
        $maintainer = $this->maintainer();

        $encoded = response()->json($maintainer)->getContent();

        $this->assertStringNotContainsString('segredo-do-mntner', $encoded);
        $this->assertStringNotContainsString('"password"', $encoded);
    }

    public function test_direct_property_access_still_returns_the_decrypted_password(): void
    {
        // $hidden só afeta serialização (toArray/toJson) — o app
        // continua conseguindo ler a senha real para publicar no TC.
        $maintainer = $this->maintainer();

        $this->assertSame('segredo-do-mntner', $maintainer->password);
        $this->assertSame('segredo-do-mntner', $maintainer->fresh()->password);
    }

    public function test_raw_dump_shows_ciphertext_not_plaintext_thanks_to_the_encrypted_cast(): void
    {
        // Eloquent não implementa __debugInfo(), então dd()/dump()/
        // var_dump()/print_r() num model não passam pelo $hidden — eles
        // percorrem o array $attributes bruto diretamente. O que salva
        // aqui não é o $hidden, é o cast 'encrypted': o valor guardado
        // em $attributes já É o ciphertext (o decrypt só acontece no
        // getAttribute() via __get). Por isso um dd($maintainer) mostra
        // um blob base64 (iv/value/mac), nunca a senha em texto puro —
        // mas só porque o cast garante isso, não o $hidden.
        $maintainer = $this->maintainer();

        $dumped = print_r($maintainer, true);

        $this->assertStringNotContainsString('segredo-do-mntner', $dumped);
        $this->assertStringContainsString('"iv":', base64_decode($this->extractPasswordCiphertext($dumped)));
    }

    private function extractPasswordCiphertext(string $dumped): string
    {
        preg_match('/\[password\] => (\S+)/', $dumped, $matches);

        return $matches[1] ?? '';
    }
}
