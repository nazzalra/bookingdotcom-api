<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\City;
use App\Models\Country;
use App\Models\Geoobject;
use App\Models\Property;
use App\Models\Role;
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
        $response->assertJsonCount(1);
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
        $response->assertJsonCount(1);
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
        $response->assertJsonCount(1);
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
        $response->assertJsonCount(1);
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
        $response->assertJsonCount(1);
        $response->assertJsonCount(1, '0.apartments');
        $response->assertJsonPath('0.apartments.0.name', $largeApartment->name);
    }
}
