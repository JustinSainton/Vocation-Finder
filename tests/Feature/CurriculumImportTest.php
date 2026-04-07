<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CurriculumImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurriculumImportTest extends TestCase
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

    public function test_guest_cannot_initiate_import(): void
    {
        $response = $this->postJson('/admin/curriculum-import', [
            'source_type' => 'pdf',
            'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
        ]);

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_initiate_import(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'pdf',
                'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_initiate_pdf_import(): void
    {
        Storage::fake('public');
        Queue::fake();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'pdf',
                'file' => UploadedFile::fake()->create('syllabus.pdf', 500, 'application/pdf'),
            ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'status']);
        $response->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('curriculum_imports', [
            'source_type' => 'pdf',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
    }

    public function test_admin_can_initiate_docx_import(): void
    {
        Storage::fake('public');
        Queue::fake();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'docx',
                'file' => UploadedFile::fake()->create('course.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);

        $response->assertCreated();
        $response->assertJson(['status' => 'pending']);
    }

    public function test_admin_can_initiate_youtube_import(): void
    {
        Queue::fake();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'youtube',
                'url' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

        $response->assertCreated();
        $response->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('curriculum_imports', [
            'source_type' => 'youtube',
            'source_path' => 'https://youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_import_requires_valid_source_type(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'exe',
                'file' => UploadedFile::fake()->create('virus.exe', 100),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('source_type');
    }

    public function test_youtube_import_requires_url(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson('/admin/curriculum-import', [
                'source_type' => 'youtube',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('url');
    }

    public function test_admin_can_check_import_status(): void
    {
        $admin = $this->adminUser();
        $import = CurriculumImport::create([
            'source_type' => 'pdf',
            'source_path' => 'test/file.pdf',
            'status' => 'processing',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/admin/curriculum-import/{$import->id}");

        $response->assertOk();
        $response->assertJson([
            'id' => $import->id,
            'status' => 'processing',
        ]);
    }

    public function test_ready_import_returns_proposed_structure(): void
    {
        $admin = $this->adminUser();
        $structure = [
            'suggested_title' => 'Test Course',
            'suggested_description' => 'A test.',
            'modules' => [
                ['title' => 'Module 1', 'description' => 'First', 'content_blocks' => [['type' => 'text', 'content' => 'Hello']]],
            ],
        ];

        $import = CurriculumImport::create([
            'source_type' => 'pdf',
            'source_path' => 'test/file.pdf',
            'status' => 'ready',
            'proposed_structure' => $structure,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/admin/curriculum-import/{$import->id}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'ready',
            'proposed_structure' => $structure,
        ]);
    }

    public function test_admin_can_confirm_import_structure(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $import = CurriculumImport::create([
            'course_id' => $course->id,
            'source_type' => 'pdf',
            'source_path' => 'test/file.pdf',
            'status' => 'ready',
            'proposed_structure' => ['modules' => []],
            'created_by' => $admin->id,
        ]);

        $structure = [
            'modules' => [
                ['title' => 'Imported Module 1', 'description' => 'From PDF', 'content_blocks' => [['type' => 'text', 'content' => '<p>Content</p>']]],
                ['title' => 'Imported Module 2', 'description' => 'More content', 'content_blocks' => []],
            ],
        ];

        $response = $this->actingAs($admin)
            ->postJson("/admin/curriculum-import/{$import->id}/confirm", [
                'course_id' => $course->id,
                'structure' => $structure,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'modules_created' => 2]);

        $this->assertDatabaseCount('course_modules', 2);
        $this->assertDatabaseHas('course_modules', [
            'course_id' => $course->id,
            'title' => 'Imported Module 1',
        ]);
    }

    public function test_cannot_confirm_import_that_is_not_ready(): void
    {
        $admin = $this->adminUser();
        $course = Course::factory()->create();

        $import = CurriculumImport::create([
            'course_id' => $course->id,
            'source_type' => 'pdf',
            'source_path' => 'test/file.pdf',
            'status' => 'processing',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/admin/curriculum-import/{$import->id}/confirm", [
                'course_id' => $course->id,
                'structure' => ['modules' => [['title' => 'Test', 'content_blocks' => []]]],
            ]);

        $response->assertUnprocessable();
    }
}
