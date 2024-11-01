<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialContanctSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('social_contact')->insert(
        [
                'social_contact_code' => 'twitter',
                'social_contact_name' => 'twitter',

            ],
            [
                'social_contact_code' => 'facebook',
                'social_contact_name' => 'facebook',

            ],
            [
                'social_contact_code' => 'tiktok',
                'social_contact_name' => 'tiktok',

            ],
            [
                'social_contact_code' => 'youtube',
                'social_contact_name' => 'youtube',

            ],
    );
    }
}
