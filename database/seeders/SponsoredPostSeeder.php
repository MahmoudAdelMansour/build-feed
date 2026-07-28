<?php

namespace Database\Seeders;

use App\Models\SponsoredPost;
use Illuminate\Database\Seeder;

class SponsoredPostSeeder extends Seeder
{
    public function run(): void
    {
        SponsoredPost::factory(5)->active()->create();
        SponsoredPost::factory(2)->inactive()->create();
    }
}
