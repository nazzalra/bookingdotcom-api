<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Bed;
use App\Models\BedType;
use App\Models\City;
use App\Models\Country;
use App\Models\Geoobject;
use App\Models\Property;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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
        $response->assertJsonCount(1, 'properties');
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
        $response->assertJsonCount(1, 'properties');
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
        $response->assertJsonCount(1, 'properties');
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
        $response->assertJsonCount(1, 'properties');
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
        $response->assertJsonCount(1, 'properties');
        $response->assertJsonCount(1, 'properties.0.apartments');
        $response->assertJsonPath('properties.0.apartments.0.name', $largeApartment->name);
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
        $response->assertJsonCount(1, 'properties');
        $response->assertJsonCount(1, 'properties.0.apartments');
        $response->assertJsonPath('properties.0.apartments.0.beds_list','');

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
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '1 '. $bedTypes[0]->name);

        // add another bed with the same type to the same room
        Bed::create([
            'room_id' => $room->id,
            'bed_type_id' => $bedTypes[0]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '2 '. str($bedTypes[0]->name)->plural());

        // add the second room with no beds
        $room2 = Room::create([
            'apartment_id' => $apartment->id,
            'room_type_id' => $roomTypes[1]->id,
            'name' => 'Kolam Room'
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '2 '. str($bedTypes[0]->name)->plural());

        // add 1 bed with the same type as previous to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[0]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '3 '. str($bedTypes[0]->name)->plural());

        // add 1 bed with different type to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[1]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '4 beds (3 '. str($bedTypes[0]->name)->plural() . ', 1 ' . $bedTypes[1]->name . ')');

        // add 1 bed with the same type as previous to the second room
        Bed::create([
            'room_id' => $room2->id,
            'bed_type_id' => $bedTypes[1]->id
        ]);

        // expect 1 property, 1 apartment on that property, 2 bed on that apartment
        $response = $this->getJson('/api/search?city=' . $cityId);
        $response->assertStatus(200);
        $response->assertJsonPath('properties.0.apartments.0.beds_list', '5 beds (3 '. str($bedTypes[0]->name)->plural() . ', 2 ' . str($bedTypes[1]->name)->plural() . ')');
    }

    public function test_property_search_returns_one_best_apartment_per_property()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);
        $largeApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Large Apartment',
            'capacity_adults' => 3,
            'capacity_children' =>2
        ]);
        $mediumApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Medium Apartment',
            'capacity_adults' => 2,
            'capacity_children' =>1
        ]);
        $smallApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' =>0
        ]);

        $response = $this->getJson('/api/search?city=' . $cityId . '&capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'properties.0.apartments');
        $response->assertJsonPath('properties.0.apartments.0.name', $mediumApartment->name);

    }
}
