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

    public function test_staff_fill_named_slots_when_adding_a_vehicle(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())
            ->post('/admin/vehicles', $this->vehiclePayload([
                'photos' => ['front' => $this->jpeg('front.jpg'), 'interior' => $this->jpeg('cabin.jpg')],
            ]))
            ->assertSessionHas('status');

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertSame(['front', 'interior'], array_keys($vehicle->images));
        $this->assertStringStartsWith('vehicles/', $vehicle->images['front']);
        Storage::disk('public')->assertExists($vehicle->images['front']);
        Storage::disk('public')->assertExists($vehicle->images['interior']);
    }

    public function test_slots_keep_their_order_and_labels_in_the_gallery(): void
    {
        $vehicle = Vehicle::factory()->create([
            'images' => ['interior' => 'vehicles/cabin.jpg', 'front' => 'vehicles/nose.jpg'],
        ]);

        $gallery = $vehicle->gallery();
        $this->assertSame(['front', 'interior'], array_column($gallery, 'slot'));
        $this->assertSame(['Front', 'Interior'], array_column($gallery, 'label'));
        $this->assertSame(['vehicles/nose.jpg', 'vehicles/cabin.jpg'], array_column($gallery, 'path'));
    }

    public function test_update_removes_a_slot_and_fills_another(): void
    {
        Storage::fake('public');

        $front = 'vehicles/old-front.jpg';
        $back = 'vehicles/old-back.jpg';
        Storage::disk('public')->put($front, 'old-one');
        Storage::disk('public')->put($back, 'old-two');
        $vehicle = Vehicle::factory()->create(['images' => ['front' => $front, 'back' => $back]]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'remove_images' => [$front],
                'photos' => ['side' => $this->jpeg('profile.jpg')],
            ]))
            ->assertSessionHas('status');

        $images = $vehicle->fresh()->images;
        $this->assertSame(['back', 'side'], array_keys($images));
        $this->assertSame($back, $images['back']);
        Storage::disk('public')->assertMissing($front);
        Storage::disk('public')->assertExists($back);
        Storage::disk('public')->assertExists($images['side']);
    }

    public function test_uploading_into_a_filled_slot_replaces_and_deletes_the_old_photo(): void
    {
        Storage::fake('public');

        $old = 'vehicles/before.jpg';
        Storage::disk('public')->put($old, 'pixel');
        $vehicle = Vehicle::factory()->create(['images' => ['front' => $old]]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'photos' => ['front' => $this->jpeg('after.jpg')],
            ]))
            ->assertSessionHas('status');

        $images = $vehicle->fresh()->images;
        $this->assertSame(['front'], array_keys($images));
        $this->assertNotSame($old, $images['front']);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($images['front']);
    }

    public function test_removals_are_matched_against_the_gallery_only(): void
    {
        Storage::fake('public');

        $kept = 'vehicles/kept.jpg';
        Storage::disk('public')->put($kept, 'keep-me');
        $vehicle = Vehicle::factory()->create(['images' => ['front' => $kept]]);

        $this->actingAs($this->staff())
            ->put(route('admin.vehicles.update', $vehicle), $this->vehiclePayload([
                'plate_number' => $vehicle->plate_number,
                'remove_images' => ['vehicles/not-in-the-gallery.jpg'],
            ]));

        $this->assertSame(['front' => $kept], $vehicle->fresh()->images);
        Storage::disk('public')->assertExists($kept);
    }

    public function test_uploads_into_unknown_slots_are_ignored(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())
            ->post('/admin/vehicles', $this->vehiclePayload([
                'photos' => ['hood' => $this->jpeg('hood.jpg'), 'front' => $this->jpeg('front.jpg')],
            ]))
            ->assertSessionHas('status');

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertSame(['front'], array_keys($vehicle->images));
    }

    public function test_a_non_image_in_a_slot_is_refused_by_name(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())
            ->post('/admin/vehicles', $this->vehiclePayload([
                'photos' => ['interior' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')],
            ]))
            ->assertSessionHasErrors('photos.interior');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_a_legacy_list_gallery_is_rekeyed_onto_slots(): void
    {
        $vehicle = Vehicle::factory()->create([
            'images' => ['vehicles/one.jpg', 'vehicles/two.jpg'],
        ]);

        $this->assertSame(
            ['front' => 'vehicles/one.jpg', 'side' => 'vehicles/two.jpg'],
            $vehicle->photoMap(),
        );
    }

    public function test_browse_cards_caption_their_carousel_slots(): void
    {
        $withPhotos = Vehicle::factory()->available()->create([
            'images' => ['front' => 'vehicles/a.jpg', 'interior' => 'vehicles/b.jpg'],
        ]);
        $withoutPhotos = Vehicle::factory()->available()->create(['images' => null]);

        $this->get('/vehicles')
            ->assertOk()
            ->assertSee('data-carousel', false)
            ->assertSee('2 photos')
            ->assertSee('Front')
            ->assertSee('Interior')
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

    public function test_detail_page_carries_the_captioned_gallery_into_the_hero(): void
    {
        $vehicle = Vehicle::factory()->available()->create([
            'images' => ['front' => 'vehicles/hero-1.jpg', 'back' => 'vehicles/hero-2.jpg'],
        ]);

        $this->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('data-carousel', false)
            ->assertSee('vehicles/hero-1.jpg')
            ->assertSee('vehicles/hero-2.jpg')
            ->assertSee('data-slide-label="Front"', false)
            ->assertSee('data-slide-label="Back"', false);
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
