<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Models\Category;
use App\Models\Channel;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_voice_channels_category(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseHas('categories', [
            'name' => 'Voice Channels',
            'position' => 2,
        ]);
    }

    public function test_seeder_creates_general_voice_channel(): void
    {
        $this->seed(ProductionSeeder::class);

        $voiceCategory = Category::where('name', 'Voice Channels')->first();

        $this->assertNotNull($voiceCategory);
        $this->assertDatabaseHas('channels', [
            'category_id' => $voiceCategory->id,
            'name' => 'General',
            'type' => ChannelType::Voice->value,
            'position' => 0,
        ]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(3, Category::count());
        $this->assertSame(5, Channel::count());
    }

    public function test_seeder_creates_all_default_categories(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertSame(3, Category::count());
        $this->assertDatabaseHas('categories', ['name' => 'General']);
        $this->assertDatabaseHas('categories', ['name' => 'Information']);
        $this->assertDatabaseHas('categories', ['name' => 'Voice Channels']);
    }

    public function test_seeder_creates_all_default_channels(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertSame(5, Channel::count());
        $this->assertSame(4, Channel::where('type', ChannelType::Text)->count());
        $this->assertSame(1, Channel::where('type', ChannelType::Voice)->count());
    }
}
