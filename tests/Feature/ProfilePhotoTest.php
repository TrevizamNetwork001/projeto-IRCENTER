<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=';

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private const WEBP_1X1 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    /**
     * PHP GD nao esta instalado neste ambiente (docker/php/Dockerfile
     * so instala bcmath/curl/intl/opcache/pcntl/pdo_pgsql/zip), entao
     * UploadedFile::fake()->image() (que gera a imagem via GD) nao
     * funciona nos testes. getimagesize() - usado pela regra "image"
     * do Laravel para validar de verdade - nao depende de GD, entao
     * usamos bytes reais e minimos de imagem em vez do gerador do
     * framework.
     */
    private function fakeImage(string $name, string $base64): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode($base64)
        );
    }

    private function viewer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ], $attributes));
    }

    public function test_user_can_upload_a_valid_jpeg_photo(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => $this->fakeImage('avatar.jpg', self::JPEG_1X1),
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('photo', $user->avatar_mode);
        $this->assertNotNull($user->avatar_photo_path);
        Storage::disk('public')->assertExists($user->avatar_photo_path);
    }

    public function test_user_can_upload_a_valid_png_photo(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => $this->fakeImage('avatar.png', self::PNG_1X1),
            ])
            ->assertRedirect();

        $this->assertSame('photo', $user->fresh()->avatar_mode);
    }

    public function test_user_can_upload_a_valid_webp_photo(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => $this->fakeImage('avatar.webp', self::WEBP_1X1),
            ])
            ->assertRedirect();

        $this->assertSame('photo', $user->fresh()->avatar_mode);
    }

    public function test_upload_rejects_invalid_mime_type(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->avatar_photo_path);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->create('big.jpg', 3000),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->avatar_photo_path);
    }

    public function test_upload_rejects_fake_image_with_image_extension(): void
    {
        // UploadedFile::fake() simula o MIME pela extensao do nome do
        // arquivo, nao pelo conteudo real - nao serve para testar a
        // deteccao real de MIME. Usamos um UploadedFile de verdade
        // (nao o dublê de teste) apontando para um arquivo texto real
        // renomeado como .jpg, para exercitar o finfo real que a regra
        // "mimes" do Laravel usa em producao.
        Storage::fake('public');

        $user = $this->viewer();

        $path = tempnam(sys_get_temp_dir(), 'fake-image');
        file_put_contents($path, 'this is plain text, not image bytes');

        $fakeImage = new UploadedFile(
            $path,
            'not-really-an-image.jpg',
            'image/jpeg',
            null,
            true
        );

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => $fakeImage,
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->avatar_photo_path);

        @unlink($path);
    }

    public function test_uploading_new_photo_replaces_and_deletes_previous_one(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('first.jpg', self::JPEG_1X1),
        ]);

        $firstPath = $user->fresh()->avatar_photo_path;

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('second.png', self::PNG_1X1),
        ]);

        $secondPath = $user->fresh()->avatar_photo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_removing_photo_falls_back_to_initials_when_no_avatar_key(): void
    {
        Storage::fake('public');

        $user = $this->viewer(['avatar_key' => null]);

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('avatar.jpg', self::JPEG_1X1),
        ]);

        $this->actingAs($user)
            ->delete(route('profile.photo.destroy'))
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('initials', $user->avatar_mode);
        $this->assertNull($user->avatar_photo_path);
    }

    public function test_removing_photo_falls_back_to_themed_avatar_when_previously_set(): void
    {
        Storage::fake('public');

        $user = $this->viewer([
            'avatar_key' => 'owl',
            'avatar_mode' => User::AVATAR_MODE_AVATAR,
        ]);

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('avatar.jpg', self::JPEG_1X1),
        ]);

        $this->actingAs($user)
            ->delete(route('profile.photo.destroy'))
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('avatar', $user->avatar_mode);
        $this->assertSame('owl', $user->avatar_key);
        $this->assertNull($user->avatar_photo_path);
    }

    public function test_guest_cannot_upload_or_remove_photo(): void
    {
        Storage::fake('public');

        $this->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('avatar.jpg', self::JPEG_1X1),
        ])->assertRedirect(route('login'));

        $this->delete(route('profile.photo.destroy'))
            ->assertRedirect(route('login'));
    }

    public function test_selecting_themed_avatar_switches_mode_from_photo(): void
    {
        Storage::fake('public');

        $user = $this->viewer();

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => $this->fakeImage('avatar.jpg', self::JPEG_1X1),
        ]);

        $this->assertSame('photo', $user->fresh()->avatar_mode);

        $this->actingAs($user)->put(route('profile.avatar.update'), [
            'avatar_key' => 'robot',
        ]);

        $user->refresh();

        $this->assertSame('avatar', $user->avatar_mode);
        $this->assertSame('robot', $user->avatar_key);
    }
}
