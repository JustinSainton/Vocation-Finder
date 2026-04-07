<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guest_cannot_upload_media(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/admin/course-media', [
            'file' => UploadedFile::fake()->image('test.jpg', 200, 200),
        ]);

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_upload_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/admin/course-media', [
                'file' => UploadedFile::fake()->image('test.jpg', 200, 200),
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_upload_image(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/course-media', [
                'file' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'id', 'url', 'original_filename', 'mime_type', 'size_bytes', 'type',
        ]);
        $response->assertJson([
            'original_filename' => 'photo.jpg',
            'type' => 'image',
        ]);

        $this->assertDatabaseHas('course_media', [
            'original_filename' => 'photo.jpg',
            'type' => 'image',
            'uploaded_by' => $admin->id,
        ]);
    }

    public function test_admin_can_upload_pdf(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/course-media', [
                'file' => UploadedFile::fake()->create('syllabus.pdf', 1024, 'application/pdf'),
            ]);

        $response->assertCreated();
        $response->assertJson([
            'original_filename' => 'syllabus.pdf',
            'type' => 'pdf',
        ]);
    }

    public function test_admin_can_upload_with_course_id(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/admin/course-media', [
                'file' => UploadedFile::fake()->image('banner.png', 800, 400),
                'course_id' => $course->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('course_media', [
            'course_id' => $course->id,
            'original_filename' => 'banner.png',
        ]);
    }

    public function test_upload_rejects_files_over_50mb(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/course-media', [
                'file' => UploadedFile::fake()->create('huge.pdf', 52000, 'application/pdf'),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_file(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/course-media', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_admin_can_view_media(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $media = CourseMedia::factory()->create(['uploaded_by' => $admin->id, 'disk' => 'public']);

        $response = $this->actingAs($admin)
            ->getJson("/admin/course-media/{$media->id}");

        $response->assertOk();
        $response->assertJsonStructure(['id', 'url', 'original_filename', 'type']);
    }

    public function test_admin_can_delete_media(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $media = CourseMedia::factory()->create(['uploaded_by' => $admin->id, 'disk' => 'public']);

        Storage::disk('public')->put($media->path, 'fake content');

        $response = $this->actingAs($admin)
            ->deleteJson("/admin/course-media/{$media->id}");

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertSoftDeleted('course_media', ['id' => $media->id]);
    }
}
