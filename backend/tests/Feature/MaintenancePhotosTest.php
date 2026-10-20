<?php

namespace Tests\Feature;

use App\Models\MaintenancePhoto;
use App\Models\MaintenanceRequest;
use App\Models\Occupancy;
use App\Models\RentalGroup;
use App\Models\RepresentativeAssignment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\TenantStay;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesBillingSchema;
use Tests\TestCase;

class MaintenancePhotosTest extends TestCase
{
    use CreatesBillingSchema;

    private User $owner;

    private User $author;

    private MaintenanceRequest $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBillingSchema();
        Storage::fake('maintenance');
        $this->owner = User::factory()->create();
        $tenant = Tenant::create(['full_name' => 'Người gửi', 'phone_number' => '0900000000']);
        $this->author = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'tenant']);
        $room = Room::create(['room_number' => 'PHOTO', 'capacity' => 1, 'default_monthly_rent' => '100', 'operational_status' => 'available']);
        $group = RentalGroup::create(['group_code' => 'PHOTO']);
        $occupancy = Occupancy::create(['room_id' => $room->id, 'rental_group_id' => $group->id, 'started_at' => '2026-01-01', 'monthly_rent' => '100']);
        $stay = TenantStay::create(['occupancy_id' => $occupancy->id, 'tenant_id' => $tenant->id, 'moved_in_at' => '2026-01-01']);
        $rep = RepresentativeAssignment::create(['occupancy_id' => $occupancy->id, 'tenant_stay_id' => $stay->id, 'started_at' => '2026-01-01']);
        $this->report = MaintenanceRequest::create(['occupancy_id' => $occupancy->id, 'submitted_by' => $this->author->id,
            'representative_assignment_id' => $rep->id, 'title' => 'Rò nước', 'description' => 'Có ảnh minh họa', 'status' => 'submitted']);
        $this->actingAs($this->author, 'web');
    }

    public function test_author_uploads_private_images_and_only_author_and_owner_can_read_them(): void
    {
        $response = $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('bathroom.jpg')]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.path')->assertJsonPath('data.0.mime_type', 'image/jpeg');
        $photo = MaintenancePhoto::first();
        Storage::disk('maintenance')->assertExists($photo->path);
        $this->get($response->json('data.0.url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/tenant/maintenance-requests/'.$this->report->id)->assertJsonCount(1, 'data.photos');
        $this->actingAs(User::factory()->create(['role' => 'tenant']), 'web');
        $this->get($response->json('data.0.url'))->assertNotFound();
        $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('other.jpg')]])->assertNotFound();
        $this->actingAs($this->owner, 'web');
        $this->get('/api/owner/maintenance-requests/'.$this->report->id.'/photos/'.$photo->id)->assertOk();
        $this->get('/api/owner/maintenance-requests/999999/photos/'.$photo->id)->assertNotFound();
        auth('web')->logout();
        $this->get($response->json('data.0.url'))->assertUnauthorized();
    }

    public function test_retries_and_duplicate_files_do_not_exceed_capacity_or_store_extra_blobs(): void
    {
        $image = UploadedFile::fake()->image('one.png');
        $bytes = file_get_contents($image->getRealPath());
        for ($i = 0; $i < 2; $i++) {
            $this->post($this->path(), ['photos' => [UploadedFile::fake()->createWithContent('one.png', $bytes), UploadedFile::fake()->createWithContent('two.png', $bytes)]])
                ->assertOk()->assertJsonCount(1, 'data');
        }
        $this->assertSame(1, MaintenancePhoto::count());
        $this->assertCount(1, Storage::disk('maintenance')->allFiles());
        $this->assertSame(1, $this->report->fresh()->revision);
    }

    public function test_five_image_limit_applies_across_multiple_requests(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('photo.jpg', $i, $i)]])->assertOk();
        }
        $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('six.jpg', 6, 6)]])->assertUnprocessable();
        $this->assertSame(5, MaintenancePhoto::count());
        $this->assertCount(5, Storage::disk('maintenance')->allFiles());
    }

    public function test_invalid_content_svg_oversized_and_oversized_dimensions_are_rejected(): void
    {
        foreach ([UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'),
            UploadedFile::fake()->image('big.jpg')->size(5121), UploadedFile::fake()->image('wide.png', 4097, 1)] as $file) {
            $this->post($this->path(), ['photos' => [$file]])->assertUnprocessable();
        }
        $this->assertSame(0, MaintenancePhoto::count());
        $this->assertCount(0, Storage::disk('maintenance')->allFiles());
    }

    public function test_upload_failure_rolls_back_rows_revision_and_new_files(): void
    {
        MaintenancePhoto::creating(fn () => throw new \RuntimeException('Simulated metadata failure'));
        try {
            $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('error.jpg')]])->assertServerError();
            $this->assertSame(0, MaintenancePhoto::count());
            $this->assertCount(0, Storage::disk('maintenance')->allFiles());
            $this->assertSame(0, $this->report->fresh()->revision);
        } finally {
            MaintenancePhoto::flushEventListeners();
        }
    }

    public function test_resolved_reports_keep_photos_readable_but_cannot_receive_more(): void
    {
        $response = $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('one.jpg')]])->assertOk();
        $this->report->update(['status' => 'resolved', 'resolution' => 'Đã sửa']);
        $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('two.jpg')]])->assertConflict();
        $this->get($response->json('data.0.url'))->assertOk();
    }

    public function test_upload_invalidates_stale_processing_version_and_requires_csrf(): void
    {
        $this->actingAs($this->owner, 'web');
        $oldToken = $this->getJson('/api/owner/maintenance-requests/'.$this->report->id)->json('data.version_token');
        $this->actingAs($this->author, 'web');
        $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('new.jpg')]])->assertOk();
        $this->actingAs($this->owner, 'web');
        $this->patchJson('/api/owner/maintenance-requests/'.$this->report->id, ['status' => 'in_progress', 'version_token' => $oldToken])->assertConflict();
        $this->actingAs($this->author, 'web');
        $this->app->instance('env', 'local');
        $this->post($this->path(), ['photos' => [UploadedFile::fake()->image('csrf.jpg')]])->assertStatus(419);
    }

    private function path(): string
    {
        return '/api/tenant/maintenance-requests/'.$this->report->id.'/photos';
    }
}
