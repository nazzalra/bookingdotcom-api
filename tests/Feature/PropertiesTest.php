<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PropertiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_owner_has_access_to_properties_feature()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $response = $this->actingAs($owner)->getJson('/api/owner/properties');
        $response->assertStatus(200);
    }

    public function test_user_does_not_have_access_to_properties_feature()
    {
        $user = User::factory()->create()->assignRole(Role::ROLE_USER);
        $response = $this->actingAs($user)->getJson('/api/owner/properties');

        $response->assertStatus(403);
    }

    public function test_owner_could_store_property()
    {
        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $response = $this->actingAs($owner)->postJson('/api/owner/properties',[
            'name' => 'My Property',
            'city_id' => City::value('id'),
            'address_street' => 'Street Address 1',
            'address_postcode' => '12345'
        ]);
        $response->assertSuccessful();
        $response->assertJsonFragment(['name' => 'My Property']);
    }

    public function test_property_owner_could_add_photos_to_property()
    {
        Storage::fake();

        $owner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $secondOwner = User::factory()->create()->assignRole(Role::ROLE_OWNER);
        $cityId = City::value('id');
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'city_id' => $cityId
        ]);

        // not owner
        $this->actingAs($secondOwner);
        $response = $this->postJson('/api/owner/properties/' . $property->id . '/photos',[
            'photo' => UploadedFile::fake()->image('photo.png')
        ]);
        $response->assertStatus(403);

        // Owner
        $this->actingAs($owner);
        $response = $this->postJson('/api/owner/properties/' . $property->id . '/photos',[
            'photo' => UploadedFile::fake()->image('photo.png')
        ]);
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'filename' => config('app.url') . '/storage/1/photo.png',
            'thumbnail' => config('app.url') . '/storage/1/conversions/photo-thumbnail.jpg'
        ]);
    }
}
