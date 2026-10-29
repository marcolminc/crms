<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleGalleryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function staff(): User
    {
        return User::factory()->fleetStaff()->create();
    }

    private function vehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'plate_number' => 'T777 GAL',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => '2022',
            'type' => 'sedan',
            'seats' => '5',
            'transmission' => 'automatic',
            'fuel_type' => 'petrol',
            'colour' => 'Silver',
            'rate_per_day' => '150000',
            'status' => 'available',
            'features' => 'AC',
        ], $overrides);
    }

    private function jpeg(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'image/jpeg');
    }

    public function test_staff_can_upload_photos_when_adding_a_vehicle(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())
            ->post('/admin/vehicles', $this->vehiclePayload([
                'photos' => [$this->jpeg('front.jpg'), $this->jpeg('side.jpg')],
            ]))
            ->assertSessionHas('status');

        $vehicle = Vehicle::query()->firstOrFail();
        $gallery = $vehicle->gallery();
        $this->assertCount(2, $gallery);
        $this->assertStringStartsWith('vehicles/', $gallery[0]);
        Storage::disk('public')->assertExists($gallery[0]);
        Storage::disk('public')->assertExists($gallery[1]);
    }

    public function test_update_keeps_the_gallery_and_deletes_removed_photos(): void
    {
        Storage::fake('public');

        $first = 'vehicles/first.jpg';
        $second = 'vehicles/second.jpg';
        Storage::disk('public')->put($first, 'old-one');
        Storage::disk('public')->put($second, 'old-two');
        $vehicle = Vehicle::factory()->create(['images' => [$first, $second]]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'remove_images' => [$first],
                'photos' => [$this->jpeg('interior.jpg')],
            ]))
            ->assertSessionHas('status');

        $gallery = $vehicle->fresh()->gallery();
        $this->assertNotContains($first, $gallery);
        $this->assertContains($second, $gallery);
        $this->assertCount(2, $gallery);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->assertStringStartsWith('vehicles/', $gallery[1]);
        Storage::disk('public')->assertExists($gallery[1]);
    }

    public function test_removals_are_matched_against_the_gallery_only(): void
    {
        Storage::fake('public');

        $kept = 'vehicles/kept.jpg';
        Storage::disk('public')->put($kept, 'keep-me');
        $vehicle = Vehicle::factory()->create(['images' => [$kept]]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'remove_images' => ['vehicles/not-in-the-gallery.jpg'],
            ]));

        $this->assertSame([$kept], $vehicle->fresh()->gallery());
        Storage::disk('public')->assertExists($kept);
    }

    public function test_the_gallery_is_capped_at_six_photos(): void
    {
        Storage::fake('public');

        $paths = [];
        for ($i = 1; $i <= Vehicle::MAX_PHOTOS; $i++) {
            $paths[] = "vehicles/full-{$i}.jpg";
            Storage::disk('public')->put($paths[$i - 1], 'x');
        }
        $vehicle = Vehicle::factory()->create(['images' => $paths]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'photos' => [$this->jpeg('one-too-many.jpg')],
            ]))
            ->assertSessionHas('error');

        $this->assertSame($paths, $vehicle->fresh()->gallery());
    }

    public function test_non_image_uploads_are_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())
            ->post('/admin/vehicles', $this->vehiclePayload([
                'photos' => [UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('photos.0');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_browse_cards_show_a_carousel_for_photoed_vehicles_and_a_fallback_without_photos(): void
    {
        $withPhotos = Vehicle::factory()->available()->create([
            'images' => ['vehicles/a.jpg', 'vehicles/b.jpg'],
        ]);
        $withoutPhotos = Vehicle::factory()->available()->create(['images' => null]);

        $this->get('/vehicles')
            ->assertOk()
            ->assertSee('data-carousel', false)
            ->assertSee('2 photos')
            ->assertSee('No photos yet')
            ->assertSee($withPhotos->plate_number)
            ->assertSee($withoutPhotos->plate_number);
    }

    public function test_a_single_photo_renders_without_the_auto_rotating_markup(): void
    {
        Vehicle::factory()->available()->create(['image_path' => 'vehicles/legacy.jpg']);

        $html = $this->get('/vehicles')->assertOk()->getContent();

        // The legacy free-text image still shows...
        $this->assertStringContainsString('vehicles/legacy.jpg', $html);
        // ...but a lone photo must not be wired as a rotating carousel.
        $this->assertStringNotContainsString('data-interval', $html);
    }

    public function test_detail_page_carries_the_gallery_into_the_hero(): void
    {
        $vehicle = Vehicle::factory()->available()->create([
            'images' => ['vehicles/hero-1.jpg', 'vehicles/hero-2.jpg'],
        ]);

        $this->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-carousel', false)
            ->assertSee('vehicles/hero-1.jpg')
            ->assertSee('vehicles/hero-2.jpg');
    }

    public function test_the_partial_endpoint_streams_the_next_page_of_cards(): void
    {
        Vehicle::factory()->count(13)->available()->create();

        $first = $this->getJson('/vehicles?partial=1')->assertOk()->json();
        $this->assertArrayHasKey('html', $first);
        $this->assertSame(12, $first['shown']);
        $this->assertSame(13, $first['total']);
        $this->assertNotNull($first['next']);
        $this->assertStringContainsString('partial=1', $first['next']);

        $second = $this->getJson($first['next'])->assertOk()->json();
        $this->assertSame(13, $second['shown']);
        $this->assertSame(13, $second['total']);
        $this->assertNull($second['next']);
        $this->assertStringContainsString('<article', $second['html']);
    }

    public function test_the_full_page_hands_the_watcher_a_next_url_when_more_pages_exist(): void
    {
        Vehicle::factory()->count(13)->available()->create();

        $this->get('/vehicles')
            ->assertOk()
            ->assertSee('data-infinite', false)
            ->assertSee('partial=1', false);
    }
}
