<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityCategory;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PropertyShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_show_loads_property_correctly()
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
            'capacity_children' => 2
        ]);

        $midSizeApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'MidSize Apartment',
            'capacity_adults' => 2,
            'capacity_children' => 1
        ]);

        $smallApartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'Small Apartment',
            'capacity_adults' => 1,
            'capacity_children' => 0
        ]);

        $facilityCategory = FacilityCategory::create(['name'=>'Some Category']);
        $facility = Facility::create(['category_id' => $facilityCategory->id, 'name' => 'Some Facility']);

        $midSizeApartment->facilities()->attach($facility->id);

        $response = $this->getJson('/api/properties/'.$property->id);
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'apartments');
        $response->assertJsonPath('name', $property->name);

        $response = $this->getJson('/api/properties/'.$property->id.'?capacity_adults=2&capacity_children=1');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'apartments');
        $response->assertJsonPath('name', $property->name);
        $response->assertJsonPath('apartments.0.facilities.0.name', $facility->name);
        $response->assertJsonCount(0, 'apartments.1.facilities');
    }
}
