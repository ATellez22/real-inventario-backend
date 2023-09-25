<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Articulo;


class ArticulosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $cities = [
            [
                'codigo'=> '1001',
                'descripcion' => 'PAN FELIPE'
            ],
            [
                'codigo'=> '1002',
                'descripcion' => 'PAN CUARTEL'
            ],
            [
                'codigo'=> '1003',
                'descripcion' => 'PAN TRINCHA'
            ],
            [
                'codigo'=> '1004',
                'descripcion' => 'PAN PEBBETE'
            ],
            [
                'codigo'=> '1005',
                'descripcion' => 'PAN PARAGUAYO'
            ],
        ];

        Articulo::truncate();

        foreach ($cities as $city) {
            Articulo::create($city);
        }
    }
}
