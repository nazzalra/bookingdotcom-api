<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ApartmentPrice;
use App\Models\City;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookingsTest extends TestCase
{
    use RefreshDatabase;

    private function create_apartment(): Apartment
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId,
        ]);

        return Apartment::factory()->create([
            'name' => 'New Apartment',
            'property_id' => $property->id,
            'capacity_adults' => 3,
            'capacity_children' => 2
        ]);
    }

    public function test_user_can_book_apartment_successfully_but_not_twice()
    {
        $user = User::factory()->create()->assignRole(Role::ROLE_USER);

        $apartment = $this->create_apartment();
        // $apartment->prices()->create([
        //     'start_date' => now()->subDays(2)->toDateString(),
        //     'end_date' => now()->addDays(2)->toDateString(),
        //     'price' => 50
        // ]);
        // $apartment->prices()->create([
        //     'start_date' => now()->addDays(3)->toDateString(),
        //     'end_date' => now()->addDays(5)->toDateString(),
        //     'price' => 150
        // ]);
        
        $payload = [
            'apartment_id' => $apartment->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 2,
            'guests_children' => 1
        ];
        
        $response = $this->actingAs($user)->postJson('/api/user/bookings', $payload);
        $response->assertStatus(201);
        $response = $this->actingAs($user)->postJson('/api/user/bookings', $payload);
        $response->assertStatus(422);

        $payload['start_date'] = now()->addDays(3);
        $payload['end_date'] = now()->addDays(4);
        $payload['guests_adults'] = 5;
        $response = $this->actingAs($user)->postJson('/api/user/bookings', $payload);
        $response->assertStatus(422);
    }

}
