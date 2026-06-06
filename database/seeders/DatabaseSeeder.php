<?php

namespace Database\Seeders;

use App\Models\Cemetery;
use App\Models\Deed;
use App\Models\Interment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Cemetery ---
        $cemetery = Cemetery::create([
            'name'    => 'Choteau Municipal Cemetery',
            'slug'    => 'choteau',
            'city'    => 'Choteau',
            'county'  => 'Teton',
            'state'   => 'MT',
            'address' => '401 N. Main St., Choteau, MT 59422',
            'notes'   => null,
        ]);

        // --- Admin user ---
        User::create([
            'name'              => env('ADMIN_NAME', 'Cemetery Admin'),
            'email'             => env('ADMIN_EMAIL', 'admin@cemetery.test'),
            'password'          => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // --- Sample deed for FK linkage ---
        $deed = Deed::create([
            'cemetery_id'  => $cemetery->id,
            'lot'          => 'LOT 4',
            'block'        => 'BLK 73',
            'grantor_name' => 'Thomas J. Reed',
            'grantee_name' => 'Margaret Reed',
            'deed_date'    => '1952-06-15',
            'notes'        => null,
        ]);

        // --- 20 diverse interments covering edge cases ---
        $records = [
            // 1. Standard burial — veteran
            [
                'last_name'        => 'Henderson',
                'first_name'       => 'Earl W.',
                'age_at_death'     => 72,
                'interment_date'   => '1988-05-14',
                'lot'              => 'LOT 4',
                'lot_number'       => 4,
                'block'            => 'BLK 73',
                'block_number'     => 73,
                'is_veteran'       => true,
                'deed_id'          => $deed->id,
                'source_notes_raw' => 'VET',
            ],

            // 2. Cremation — ashes at foot
            [
                'last_name'           => 'Pratt',
                'first_name'          => 'Lucille Mae',
                'age_at_death'        => 84,
                'interment_date'      => '2001-09-22',
                'lot'                 => 'LOT 7',
                'lot_number'          => 7,
                'block'               => 'BLK 91',
                'block_number'        => 91,
                'is_cremation'        => true,
                'cremation_placement' => 'foot',
                'source_notes_raw'    => 'ashes at foot',
            ],

            // 3. Cremation — ashes at head
            [
                'last_name'           => 'Walton',
                'first_name'          => 'George R.',
                'age_at_death'        => 67,
                'interment_date'      => '1995-03-08',
                'lot'                 => 'LOT 2',
                'lot_number'          => 2,
                'block'               => 'BLK 64',
                'block_number'        => 64,
                'is_cremation'        => true,
                'cremation_placement' => 'head',
                'source_notes_raw'    => 'Ashes at head',
            ],

            // 4. Cremation — ashes only (no casket)
            [
                'last_name'           => 'Sorenson',
                'first_name'          => 'Myrtle A.',
                'age_at_death'        => 91,
                'interment_date'      => '2010-11-30',
                'lot'                 => 'LOT 11',
                'lot_number'          => 11,
                'block'               => 'BLK 102',
                'block_number'        => 102,
                'is_cremation'        => true,
                'cremation_placement' => 'only',
                'source_notes_raw'    => 'ASHES ONLY',
            ],

            // 5. Infant
            [
                'last_name'        => 'Brennan',
                'first_name'       => 'Baby',
                'age_at_death'     => null,
                'age_raw'          => 'infant',
                'interment_date'   => '1934-07-01',
                'lot'              => 'LOT 3',
                'lot_number'       => 3,
                'block'            => 'BLK 58',
                'block_number'     => 58,
                'is_infant'        => true,
                'source_notes_raw' => '(infant)',
            ],

            // 6. Infant with age in months
            [
                'last_name'        => 'Aitch',
                'first_name'       => 'McKenzie Rae',
                'age_at_death'     => null,
                'age_raw'          => '3 mos.',
                'interment_date'   => '1961-02-14',
                'lot'              => 'LOT 6',
                'lot_number'       => 6,
                'block'            => 'BLK 77',
                'block_number'     => 77,
                'is_infant'        => true,
                'source_notes_raw' => '(Baby) 3 mos.',
            ],

            // 7. Disinterment
            [
                'last_name'        => 'Calloway',
                'first_name'       => 'James T.',
                'age_at_death'     => 58,
                'interment_date'   => '1989-04-12',
                'lot'              => 'LOT 9',
                'lot_number'       => 9,
                'block'            => 'BLK 85',
                'block_number'     => 85,
                'is_disinterment'  => true,
                'notes'            => 'disinterment 11/28/00',
                'source_notes_raw' => 'disinterment 11/28/00',
            ],

            // 8. North half-lot qualifier
            [
                'last_name'        => 'Blackwood',
                'first_name'       => 'Harriet E.',
                'age_at_death'     => 79,
                'interment_date'   => '1952-08-19',
                'lot'              => 'LOT 8 N 1/2',
                'lot_number'       => 8,
                'lot_qualifier'    => 'N 1/2',
                'block'            => 'BLK 73',
                'block_number'     => 73,
                'source_notes_raw' => '',
            ],

            // 9. South half-lot qualifier
            [
                'last_name'        => 'Blackwood',
                'first_name'       => 'Walter C.',
                'age_at_death'     => 82,
                'interment_date'   => '1958-03-03',
                'lot'              => 'LOT 8 S. 1/2',
                'lot_number'       => 8,
                'lot_qualifier'    => 'S. 1/2',
                'block'            => 'BLK 73',
                'block_number'     => 73,
                'source_notes_raw' => '',
            ],

            // 10. Block with letter suffix (BLK 64B)
            [
                'last_name'        => 'Nguyen',
                'first_name'       => 'Phong D.',
                'age_at_death'     => 44,
                'interment_date'   => '2003-01-27',
                'lot'              => 'LOT 5',
                'lot_number'       => 5,
                'block'            => 'BLK 64B',
                'block_number'     => 64,
                'block_suffix'     => 'B',
                'source_notes_raw' => '',
            ],

            // 11. Block with letter suffix (BLK 102B)
            [
                'last_name'        => 'Olsen',
                'first_name'       => 'Ingrid',
                'age_at_death'     => 88,
                'interment_date'   => '1977-12-05',
                'lot'              => 'LOT 13',
                'lot_number'       => 13,
                'block'            => 'BLK 102B',
                'block_number'     => 102,
                'block_suffix'     => 'B',
                'source_notes_raw' => '',
            ],

            // 12. Veteran + full cremation
            [
                'last_name'           => 'Murphy',
                'first_name'          => 'Patrick J.',
                'age_at_death'        => 88,
                'interment_date'      => '2018-06-11',
                'lot'                 => 'LOT 1',
                'lot_number'          => 1,
                'block'               => 'BLK 114',
                'block_number'        => 114,
                'is_veteran'          => true,
                'is_cremation'        => true,
                'cremation_placement' => 'full',
                'source_notes_raw'    => 'VET full cremation',
            ],

            // 13. Veteran spouse — NOT marked is_veteran per spec
            [
                'last_name'        => 'Murphy',
                'first_name'       => 'Dorothy L.',
                'age_at_death'     => 81,
                'interment_date'   => '2021-03-14',
                'lot'              => 'LOT 1',
                'lot_number'       => 1,
                'block'            => 'BLK 114',
                'block_number'     => 114,
                'is_veteran'       => false,
                'notes'            => 'Veteran Spouse',
                'source_notes_raw' => 'Veteran Spouse',
            ],

            // 14. Age embedded in name (raw string preserved)
            [
                'last_name'          => 'Adams',
                'first_name'         => 'John',
                'age_at_death'       => 87,
                'age_raw'            => '87',
                'interment_date'     => '1945-10-01',
                'interment_date_raw' => 'Oct 1945',
                'lot'                => 'LOT 4',
                'lot_number'         => 4,
                'block'              => 'BLK 58',
                'block_number'       => 58,
                'source_notes_raw'   => 'Adams, John -87-',
            ],

            // 15. Approximate interment date — no clean date
            [
                'last_name'          => 'Fitzgerald',
                'first_name'         => 'Clara B.',
                'age_at_death'       => 34,
                'interment_date'     => null,
                'interment_date_raw' => 'circa 1918',
                'lot'                => 'LOT 10',
                'lot_number'         => 10,
                'block'              => 'BLK 60',
                'block_number'       => 60,
                'source_notes_raw'   => 'circa 1918',
            ],

            // 16. No identity information — unknown
            [
                'last_name'          => 'Unknown',
                'first_name'         => null,
                'age_at_death'       => null,
                'interment_date'     => null,
                'interment_date_raw' => 'May 1922',
                'lot'                => 'LOT 15',
                'lot_number'         => 15,
                'block'              => 'BLK 67',
                'block_number'       => 67,
                'notes'              => 'No identifying information',
                'source_notes_raw'   => '',
            ],

            // 17. East half-lot qualifier
            [
                'last_name'        => 'Lindstrom',
                'first_name'       => 'Erik J.',
                'age_at_death'     => 55,
                'interment_date'   => '1962-09-18',
                'lot'              => 'LOT 13 E1/2',
                'lot_number'       => 13,
                'lot_qualifier'    => 'E1/2',
                'block'            => 'BLK 88',
                'block_number'     => 88,
                'source_notes_raw' => '',
            ],

            // 18. Compound lot qualifier (E1/2 S/12)
            [
                'last_name'        => 'Lindstrom',
                'first_name'       => 'Anna M.',
                'age_at_death'     => 49,
                'interment_date'   => '1955-02-21',
                'lot'              => 'LOT 13 E1/2 S/12',
                'lot_number'       => 13,
                'lot_qualifier'    => 'E1/2 S/12',
                'block'            => 'BLK 88',
                'block_number'     => 88,
                'notes'            => 'Compound lot qualifier from source',
                'source_notes_raw' => '',
            ],

            // 19. Cremation with GPS plot coordinates
            [
                'last_name'           => 'Tanner',
                'first_name'          => 'Ruth Ellen',
                'age_at_death'        => 77,
                'interment_date'      => '2019-08-05',
                'lot'                 => 'LOT 3',
                'lot_number'          => 3,
                'block'               => 'BLK 91',
                'block_number'        => 91,
                'is_cremation'        => true,
                'cremation_placement' => 'foot',
                'plot_coordinates'    => ['lat' => 47.854321, 'lng' => -112.183456],
                'source_notes_raw'    => 'ashes at foot',
            ],

            // 20. Newborn — age in hours, infant flag
            [
                'last_name'        => 'Morrison',
                'first_name'       => 'Baby',
                'age_at_death'     => null,
                'age_raw'          => '24 HRS OLD',
                'interment_date'   => '1948-11-03',
                'lot'              => 'LOT 2',
                'lot_number'       => 2,
                'block'            => 'BLK 58',
                'block_number'     => 58,
                'is_infant'        => true,
                'source_notes_raw' => '24 HRS OLD',
            ],
        ];

        foreach ($records as $data) {
            Interment::create(array_merge(['cemetery_id' => $cemetery->id], $data));
        }
    }
}
