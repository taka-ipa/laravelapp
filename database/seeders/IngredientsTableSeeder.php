<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IngredientsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipes = DB::table('recipes')->pluck('id')->toArray(); //recipes テーブルのすべてのidを引き抜いて（取得）、配列として $recipes に格納。
        $ingredient_names = ['Salt', 'Sugar', 'Flour', 'Eggs', 'Milk', 'Butter', 'Oil', 'Vanilla extract', 'Baking powder', 'Cocoa powder'];

        foreach ($recipes as $recipe) {
            for ($i = 0; $i < rand(2, 5); $i++) { // 2 から 5 の間のランダムな数を生成し、その数だけループを回します。
                DB::table('ingredients')->insert([
                    'recipe_id' => $recipe,
                    'name' => $ingredient_names[array_rand($ingredient_names)],// $ingredient_namesの中からランダムに選んだインデックス要素を取り出す。['salt']ならsaltが取り出せる。
                    'quantity' => rand(1, 500) . 'g', // Random quantity between 1 and 500 grams
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
