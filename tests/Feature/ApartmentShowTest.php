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

class ApartmentShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_apartment_show_loads_apartment_with_facilities()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);

        $apartment = Apartment::factory()->create([
            'property_id' => $property->id,
            'name' => 'My Apartment',
            'capacity_adults' => 3,
            'capacity_children' => 2
        ]);

        $firstFacilityCategory = FacilityCategory::create([
            'name'=> 'First Category',
        ]);
        $secondFacilityCategory = FacilityCategory::create([
            'name'=> 'Second Category',
        ]);

        $firstFacility = Facility::create([
            'category_id' => $firstFacilityCategory->id,
            'name' => 'first facility'
        ]);
        $secondFacility = Facility::create([
            'category_id' => $firstFacilityCategory->id,
            'name' => 'second facility'
        ]);
        $thirdFacility = Facility::create([
            'category_id' => $secondFacilityCategory->id,
            'name' => 'third facility'
        ]);

        $apartment->facilities()->attach([
            $firstFacility->id,
            $secondFacility->id,
            $thirdFacility->id,
        ]);

        $response = $this->getJson('/api/apartments/'. $apartment->id);
        $response->assertStatus(200);
        $response->assertJsonPath('name', $apartment->name);
        
        $expectedFacilityArray = [
            $firstFacilityCategory->name => [
                $firstFacility->name,
                $secondFacility->name
            ],
            $secondFacilityCategory->name => [
                $thirdFacility->name
            ]
        ];

        $response->assertJsonFragment($expectedFacilityArray, 'facility_categories');
        
    }
}
