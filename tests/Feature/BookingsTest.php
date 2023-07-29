<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ApartmentPrice;
use App\Models\Booking;
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

    public function test_user_can_get_only_their_bookings()
    {
        $user1 = User::factory()->create()->assignRole(Role::ROLE_USER);
        $user2 = User::factory()->create()->assignRole(Role::ROLE_USER);

        $apartment1 = $this->create_apartment();
        $apartment2 = $this->create_apartment();
        
        $booking1 = Booking::create([
            'apartment_id' => $apartment1->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 1,
            'guests_children' => 0,
        ]);
        $booking2 = Booking::create([
            'apartment_id' => $apartment2->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 2,
            'guests_children' => 1,
        ]);


        $response = $this->actingAs($user1)->getJson('/api/user/bookings');
        $response->assertStatus(200)
                    ->assertJsonCount(2);

        $response = $this->actingAs($user1)->getJson('/api/user/bookings/'. $booking1->id);
        $response->assertStatus(200)
                    ->assertJsonFragment(['guests_adults'=>1]);

        $response = $this->actingAs($user2)->getJson('/api/user/bookings');
        $response->assertStatus(200)
                    ->assertJsonCount(0);
    }

    public function test_user_can_cancel_their_booking_but_still_can_view_it()
    {
        $user1 = User::factory()->create()->assignRole(Role::ROLE_USER);
        $user2 = User::factory()->create()->assignRole(Role::ROLE_USER);

        $apartment1 = $this->create_apartment();
        $apartment2 = $this->create_apartment();
        
        $booking1 = Booking::create([
            'apartment_id' => $apartment1->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 1,
            'guests_children' => 0,
        ]);
        $booking2 = Booking::create([
            'apartment_id' => $apartment2->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 2,
            'guests_children' => 1,
        ]);

        $response = $this->actingAs($user1)->getJson('/api/user/bookings');
        $response->assertStatus(200)
                    ->assertJsonCount(2);
        
        $response = $this->actingAs($user2)->deleteJson('/api/user/bookings/' . $booking1->id);
        $response->assertStatus(403);

        $response = $this->actingAs($user1)->deleteJson('/api/user/bookings/' . $booking1->id);
        $response->assertStatus(204);

        $response = $this->actingAs($user1)->getJson('/api/user/bookings');
        $response->assertStatus(200)
                    ->assertJsonCount(2)
                    ->assertJsonFragment(['cancelled_at'=> now()->toDateString()]);

        $response = $this->actingAs($user1)->getJson('/api/user/bookings/'. $booking1->id);
        $response->assertStatus(200)
                    ->assertJsonFragment(['cancelled_at'=> now()->toDateString()]);
    }

}
