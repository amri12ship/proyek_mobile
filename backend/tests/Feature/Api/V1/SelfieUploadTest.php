<?php

namespace Tests\Feature\Api\V1;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfieUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(): array
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->for($user)->create();

        Sanctum::actingAs($user);

        return [$user, $employee];
    }

    public function test_selfie_can_be_uploaded_and_is_stored_on_the_private_disk(): void
    {
        [$user] = $this->signIn();

        $response = $this->post('/api/v1/attendance/selfie', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'data' => ['path', 'size', 'mime_type', 'uploaded_at']]);

        $this->assertGreaterThan(0, $response->json('data.size'));

        $path = $response->json('data.path');

        $this->assertStringStartsWith('selfies/'.$user->id.'/', $path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/v1/attendance/selfie')->assertUnauthorized();
    }

    public function test_upload_rejects_non_image_and_oversized_files(): void
    {
        $this->signIn();

        $this->post('/api/v1/attendance/selfie', [
            'selfie' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('selfie');

        $this->post('/api/v1/attendance/selfie', [
            'selfie' => UploadedFile::fake()->create('selfie.jpg', 5000, 'image/jpeg'),
        ])->assertStatus(422)->assertJsonValidationErrors('selfie');

        $this->post('/api/v1/attendance/selfie', [])->assertStatus(422)->assertJsonValidationErrors('selfie');
    }

    public function test_admin_cannot_upload_a_selfie(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->post('/api/v1/attendance/selfie', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertForbidden();
    }

    public function test_user_without_employee_profile_cannot_upload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/attendance/selfie', [
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertForbidden()->assertJsonPath('code', 'EMPLOYEE_PROFILE_MISSING');
    }
}
