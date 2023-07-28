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

class ApartmentPriceTest extends TestCase
{
    use RefreshDatabase;

    private function createApartment()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId,
        ]);
 
        return Apartment::create([
            'name' => 'Apartment',
            'property_id' => $property->id,
            'capacity_adults' => 3,
            'capacity_children' => 2,
        ]);
    }

    public function test_apartment_calculate_price_for_1_day()
    {
        $apartment = $this->createApartment();
        ApartmentPrice::create([
            'apartment_id' => $apartment->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'price' => 100
        ]);
        $cost = $apartment->calculatePriceForDates(now()->toDateString(), now()->toDateString());
        $this->assertEquals(100, $cost);
    }

    public function test_apartment_calculate_price_for_2_days()
    {
        $apartment = $this->createApartment();
        ApartmentPrice::create([
            'apartment_id' => $apartment->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'price' => 100
        ]);
        $cost = $apartment->calculatePriceForDates(now()->toDateString(), now()->addDay()->toDateString());
        $this->assertEquals(200, $cost);
    }

    public function test_apartment_calculate_price_for_multiple_range()
    {
        $apartment = $this->createApartment();
        ApartmentPrice::create([
            'apartment_id' => $apartment->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'price' => 100
        ]);

        ApartmentPrice::create([
            'apartment_id' => $apartment->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'price' => 10
        ]);
        $cost = $apartment->calculatePriceForDates(now()->toDateString(), now()->addDays(3)->toDateString());
        $this->assertEquals(3 * 100 + 1 * 10, $cost);
    }
}
