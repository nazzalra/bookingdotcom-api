<?php

namespace Database\Seeders\Performance;

use App\Models\Apartment;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(int $withRatings = 100, int $withoutRatings = 100): void
    {
        $users = User::role(Role::ROLE_USER)->pluck('id');
        $apartmentMin = Apartment::min('id');
        $apartmentMax = Apartment::max('id');
        $bookings = [];
        for($i = 1; $i <= $withoutRatings; $i++){
            $start_date = now()->addDays(rand(1,200));
            $bookings[] = [
                'apartment_id' => rand($apartmentMin, $apartmentMax),
                'user_id' => $users->random(),
                'start_date' => $start_date->toDateTimeString(),
                'end_date' => $start_date->addDays(rand(2,7))->toDateTimeString(),
                'guests_adults' => rand(1,5),
                'guests_children' => rand(1,5),
                'total_price' => rand(100,2000),
                'rating' => null,
            ];

            if($i % 400 == 0 || $i == $withoutRatings){
                Booking::insert($bookings);
                $bookings = [];
            }
        }
        for($i = 1; $i <= $withRatings; $i++){
            $start_date = now()->addDays(rand(1,200));
            $bookings[] = [
                'apartment_id' => rand($apartmentMin, $apartmentMax),
                'user_id' => $users->random(),
                'start_date' => $start_date->toDateTimeString(),
                'end_date' => $start_date->addDays(rand(2,7))->toDateTimeString(),
                'guests_adults' => rand(1,5),
                'guests_children' => rand(1,5),
                'total_price' => rand(100,2000),
                'rating' => random_int(1,10),
            ];

            if($i % 400 == 0 || $i == $withRatings){
                Booking::insert($bookings);
                $bookings = [];
            }
        }
        
    }
}
