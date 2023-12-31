<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ApartmentPrice;
use App\Models\Bed;
use App\Models\BedType;
use App\Models\Booking;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Geoobject;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_property_by_city()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cities = City::take(2)->pluck('id');
        $propertyInCity = Property::factory()->create(['owner_id' => $owner->id,'city_id' => $cities[0]]);
        $propertyInAnotherCity = Property::factory()->create(['owner_id' => $owner->id,'city_id' => $cities[1]]);

        $response = $this->getJson('/api/search?city=' . $cities[0]);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonFragment(['id' => $propertyInCity->id]);
    }

    public function test_search_property_by_country()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $countries = Country::with('cities')->take(2)->get();
        $propertyInCountry = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $countries[0]->cities()->value('id')
        ]);
        $propertyInAnotherCountry = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $countries[1]->cities()->value('id')
        ]);
        
        $response = $this->getJson('/api/search?country=' . $countries[0]->id);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonFragment(['id' => $propertyInCountry->id]);
    }

    public function test_search_property_by_geoobject()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $geoobject = Geoobject::first();
        $propertyNear = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => City::value('id'),
            'lat' => $geoobject->lat,
            'long' => $geoobject->long
        ]);
        $propertyFar = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => City::value('id'),
            'lat' =>$geoobject->lat + 10,
            'long' =>$geoobject->long - 10
        ]);
        $response = $this->getJson('/api/search?geoobject=' . $geoobject->id);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonFragment(['id' => $propertyNear->id]);
    }

    public function test_search_property_by_capacity_adults_and_capacity_children()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $city = City::value('id');
        $propertyWithSmallApartment = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $city,
        ]);
        Apartment::factory()->create([
            'property_id' => $propertyWithSmallApartment->id,
            'capacity_adults' => 1,
            'capacity_children' => 0
        ]);

        $propertyWithLargeApartment = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $city,
        ]);
        Apartment::factory()->create([
            'property_id' => $propertyWithLargeApartment->id,
            'capacity_adults' => 3,
            'capacity_children' => 2
        ]);

        $response = $this->getJson('/api/search?city=' . $city . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonFragment(['id' => $propertyWithLargeApartment->id]);
    }

    public function test_search_property_by_capacity_only_return_suitable_apartment()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $smallApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' => 0
        ]);
        $largeApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Large Apartment',
            'capacity_adults' => 3,
            'capacity_children' => 2
        ]);

        $response = $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonCount(1, 'properties.data.0.apartments');
        $response->assertJsonPath('properties.data.0.apartments.0.name', $largeApartment->name);
    }

    public function test_property_search_beds_list_all_cases()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $roomTypes = RoomType::all();
        $bedTypes = BedType::all();

        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $apartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' => 0
        ]);

        // expect 1 property, 1 apartment on that property, 0 beds on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data');
        $response->assertJsonCount(1, 'properties.data.0.apartments');
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list','');

        // create 1 room with 1 bed on that apartment
        $room = Room::create([
            'apartment_id' => $apartment->id,
            'room_type_id' => $roomTypes[0]->id,
            'name' => 'Luxury Room'
        ]);

        Bed::create([
            'room_id' => $room->id,
            'bed_type_id' => $bedTypes[0]->id
        ]);

        // expect 1 property, 1 apartment on that property, 1 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '1 '. $bedTypes[0]->name);

        // add another bed with the same type to the same room
        Bed::create([
            'room_id' => $room->id,
            'bed_type_id' => $bedTypes[0]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '2 '. str($bedTypes[0]->name)->plural());

        // add the second room with no beds
        $room2 = Room::create([
            'apartment_id' => $apartment->id,
            'room_type_id' => $roomTypes[1]->id,
            'name' => 'Kolam Room'
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '2 '. str($bedTypes[0]->name)->plural());

        // add 1 bed with the same type as previous to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[0]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '3 '. str($bedTypes[0]->name)->plural());

        // add 1 bed with different type to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[1]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '4 beds (3 '. str($bedTypes[0]->name)->plural() . ', 1 ' . $bedTypes[1]->name . ')');

        // add 1 bed with the same type as previous to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[1]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.data.0.apartments.0.beds_list', '5 beds (3 '. str($bedTypes[0]->name)->plural() . ', 2 ' . str($bedTypes[1]->name)->plural() . ')');
    }

    public function test_property_search_returns_one_best_apartment_per_property()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        
         // case 1 : property1 has 3 apartments, do property search, 1 best match apartment returned
        $property1 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $largeApartment = Apartment::factory()->create([
            'property_id' => $property1->id,
            'name' => 'Large Apartment',
            'capacity_adults' => 3,
            'capacity_children' =>2
        ]);
        $mediumApartment = Apartment::factory()->create([
            'property_id' => $property1->id,
            'name' => 'Medium Apartment',
            'capacity_adults' => 2,
            'capacity_children' =>1
        ]);
        $smallApartment = Apartment::factory()->create([
            'property_id' => $property1->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' =>0
        ]);

        $response = $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data.0.apartments');
        $response->assertJsonPath('properties.data.0.apartments.0.name', $mediumApartment->name);

         // case 2 : property2 has 3 apartments, do property search, 2 best match apartment returned (1 per property)
        $property2 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $largeApartment = Apartment::factory()->create([
            'property_id' => $property2->id,
            'name' => 'Large Apartment',
            'capacity_adults' => 3,
            'capacity_children' =>2
        ]);
        $mediumApartment = Apartment::factory()->create([
            'property_id' => $property2->id,
            'name' => 'Medium Apartment',
            'capacity_adults' => 2,
            'capacity_children' =>1
        ]);
        $smallApartment = Apartment::factory()->create([
            'property_id' => $property2->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' =>0
        ]);

        $response = $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.data.1.apartments');
        $response->assertJsonPath('properties.data.1.apartments.0.name', $mediumApartment->name);

    }

    public function test_property_search_filter_by_facilities()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $smallApartment = Apartment::factory()->create([
            'name' => 'Small Apartment',
            'property_id' => $property->id,
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        $property2 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $largeApartment = Apartment::factory()->create([
            'name' => 'Large Apartment',
            'property_id' => $property2->id,
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        // Case 1: No Facility filter, 2 properties returned
        $this->getJson('/api/search?city='.$cityId . '&capacity_adults=2&capacity_children=1')
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');

        // Case 2: Filter by facility, 0 properties returned
        $facility = Facility::create(['name'=>'New Facility']);
        $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1' . '&facilities[]=' . $facility->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'properties.data');

        // Case 3: Attach facility to the first property, Filter by facility, 1 properties returned
        $property->facilities()->attach($facility->id);
        $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1' . '&facilities[]=' . $facility->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'properties.data');

        // Case 4: Attach facility to the second property, Filter by facility, 2 properties returned
        $property2->facilities()->attach($facility->id);
        $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1' . '&facilities[]=' . $facility->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');
    }

    public function test_property_search_include_photos_thumbnail_per_property()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);

        $photo1 = $this->actingAs($owner)->postJson('/api/owner/properties/' . $property->id . '/photos', [
            'photo' => UploadedFile::fake()->image('photo1.png')
        ]);
        $photo2 = $this->actingAs($owner)->postJson('/api/owner/properties/' . $property->id . '/photos', [
            'photo' => UploadedFile::fake()->image('photo2.png')
        ]);

        $response = $this->getJson('/api/search?city='. $cityId);
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'properties.data.0.photos');
        $response->assertJsonFragment(['photos'=>[$photo1['thumbnail'], $photo2['thumbnail']]]);
        
    }

    public function test_search_property_filters_by_price()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);

        $cheapApartment = Apartment::factory()->create([
            'name' => 'Small Apartment',
            'property_id' => $property->id,
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        $cheapApartment->prices()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth(),
            'price' => 30
        ]);

        $property2 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);

        $expensiveApartment = Apartment::factory()->create([
            'name' => 'Big Apartment',
            'property_id' => $property2->id,
            'capacity_adults' => 5,
            'capacity_children' => 3
        ]);

        $expensiveApartment->prices()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth(),
            'price' => 200
        ]);

        $this->getJson('/api/search?city=' . $cityId)
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');

        // price from
        $this->getJson('/api/search?city=' . $cityId . '&price_from=10')
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_from=40')
            ->assertStatus(200)
            ->assertJsonCount(1, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_from=240')
            ->assertStatus(200)
            ->assertJsonCount(0, 'properties.data');

        // price to
        $this->getJson('/api/search?city=' . $cityId . '&price_to=10')
            ->assertStatus(200)
            ->assertJsonCount(0, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_to=40')
            ->assertStatus(200)
            ->assertJsonCount(1, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_to=240')
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');

            
        // price both price_from and price_to

        $this->getJson('/api/search?city=' . $cityId . '&price_from=10&price_to=40')
            ->assertStatus(200)
            ->assertJsonCount(1, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_from=10&price_to=350')
            ->assertStatus(200)
            ->assertJsonCount(2, 'properties.data');

        $this->getJson('/api/search?city=' . $cityId . '&price_from=200&price_to=350')
            ->assertStatus(200)
            ->assertJsonCount(1, 'properties.data');

    }

    public function test_property_search_include_avg_rating_and_ordered_by_it()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');

        $property1 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $apartment1 = Apartment::factory()->create([
            'property_id' => $property1->id,
            'name' => 'Apartment 1',
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        $property2 = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $apartment2 = Apartment::factory()->create([
            'property_id' => $property2->id,
            'name' => 'Apartment 2',
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        // create 2 user
        $user1 = User::factory()->create()->assignRole(Role::ROLE_USER);
        $user2 = User::factory()->create()->assignRole(Role::ROLE_USER);

        // create 3 booking, user1 book apartment1 and apartment2, user2 book apartment2

        Booking::create([
            'apartment_id' => $apartment1->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 1,
            'guests_children' => 0,
            'rating' => 7
        ]);

        Booking::create([
            'apartment_id' => $apartment2->id,
            'user_id' => $user1->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 1,
            'guests_children' => 0,
            'rating' => 9
        ]);

        Booking::create([
            'apartment_id' => $apartment2->id,
            'user_id' => $user2->id,
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(2),
            'guests_adults' => 1,
            'guests_children' => 1,
            'rating' => 7
        ]);

        $response = $this->getJson('/api/search?city' . $cityId . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(2,'properties.data');
        
        $this->assertEquals(8, $response->json('properties.data')[0]['avg_rating']);
        $this->assertEquals(7, $response->json('properties.data')[1]['avg_rating']);
    }
}
