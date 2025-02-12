<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Http\Requests\RecipeCreateRequest;
use App\Http\Requests\RecipeUpdateRequest;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use App\Models\Recipe;
use App\Models\Step;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Review;

class RecipeController extends Controller
{
    public function home()
    {
        // get all recipes
        $recipes = Recipe::select('recipes.id', 'recipes.title', 'recipes.description', 'recipes.created_at', 'recipes.image', 'users.name')
            ->join('users', 'users.id', '=', 'recipes.user_id')
            ->orderBy('recipes.created_at', 'desc')
            ->limit(3)
            ->get();
        // dd($recipes);

        $popular = Recipe::select('recipes.id', 'recipes.title', 'recipes.description', 'recipes.created_at', 'recipes.image', 'recipes.views', 'users.name')
            ->join('users', 'users.id', '=', 'recipes.user_id')
            ->orderBy('recipes.views', 'desc')
            ->limit(2)
            ->get();
            // dd($popular);

        return view('home', compact('recipes', 'popular'));
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        // dd($filters);
        $query = Recipe::query()->select('recipes.id', 'recipes.title', 'recipes.description', 'recipes.created_at', 'recipes.image', 'users.name'
        , \DB::raw('AVG(reviews.rating) as rating'))
            ->join('users', 'users.id', '=', 'recipes.user_id')
            ->leftJoin('reviews', 'reviews.recipe_id', '=', 'recipes.id')
            ->groupBy('recipes.id')
            ->orderBy('recipes.created_at', 'desc');

        if( !empty($filters) ) {
                // もしカテゴリーが選択されていたら
            if( !empty($filters['categories']) ) {
                // カテゴリーで絞り込み選択したカテゴリーIDが含まれているレシピを取得
                $query->whereIn('recipes.category_id', $filters['categories']);
            }
            if( !empty($filters['rating']) ) {
                // 評価で絞り込み
                $query->havingRaw('AVG(reviews.rating) >= ?', [$filters['rating']])->orderBy('rating', 'desc');
            }
            if( !empty($filters['title']) ) {
                // タイトルで絞り込み
                // さつまいも
                // さつまいもの甘露煮
                // 美味しいさつまいもの甘露煮
                $query->where('recipes.title', 'like', '%'.$filters['title'].'%');
            }
        }
        $recipes = $query->paginate(5);
        // dd($resipes);

        $categories = Category::all();
        return view('recipes.index', compact('recipes', 'categories', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::all();

        return view('recipes.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecipeCreateRequest $request)
    {
        $posts = $request->all();
        $uuid = Str::uuid()->toString();
        // dd($posts);
        $image = $request->file('image');
        // s3に画像をアップロード
        $path = Storage::disk('s3')->putFile('recipe', $image, 'public');
        // dd($path);
        // s3のURLを取得
        $url = Storage::disk('s3')->url($path);
        // DBにはURLを保存
        try {
            DB::beginTransaction();
        Recipe::insert([
            'id' => $uuid,
            'title' => $posts['title'],
            'description' => $posts['description'],
            'category_id' => $posts['category'],
            'image' => $url,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        // $posts['ingredients'] =$posts['ingredients'][0]['name']
        // $posts['ingredients'] =$posts['ingredients'][0]['quantity']
        $ingredients = [];
        foreach($posts['ingredients'] as $key => $ingredient){
            $ingredients[$key] = [
                'recipe_id' => $uuid,
                'name' => $ingredient['name'],
                'quantity' => $ingredient['quantity'],
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        Ingredient::insert($ingredients);
        $steps = [];
        foreach($posts['steps'] as $key => $step){
            $steps[$key] = [
                'recipe_id' => $uuid,
                'step_number' => $key + 1,
                'description' => $step,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        STEP::insert($steps);
        DB::commit();
    } catch (\Exception $th) {
        DB::rollBack();
        \Log::error($th->getMessage());
        throw $th;
    }
        flash()->success('レシピを投稿しました!');
        
        return redirect()->route('recipe.show', ['id' => $uuid]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $recipe = Recipe::with(['ingredients', 'steps', 'reviews.user', 'user']) // レシピモデルからリレーションで材料とステップを取得
            ->where('recipes.id', $id)  // レシピIDで絞り込み
            ->firstOrFail(); // 最初の1件を直接取得。レコードが見つからない場合は例外をスロー
        // $recipe = $recipe[0]; // 1件目を取得。レコードが見つからない場合はnullを返す
        // レシピの閲覧数をインクリメント
        $recipe_recode = Recipe::find($id);
        $recipe_recode->increment('views');

        // レシピの投稿者がログインユーザーかどうかを判定
        $is_my_recipe = false;
        if(Auth::check() && $recipe->user->id === Auth::id()) { //ログインしているかつ、レシピの投稿者IDとログインユーザーIDが一致する場合
            $is_my_recipe = true;
        }

        $is_reviewed = false;
        if(Auth::check()) {
            $is_reviewed = $recipe->reviews->contains('user_id', Auth::id());
        }

        // $ingredients = Ingredient::where('recipe_id', $recipe['id'])->get();
        // $steps = Step::where('recipe_id', $recipe['id'])->get();
        // リレーションで材料とステップを取得
        // dd($recipe);

        return view('recipes.show', compact('recipe', 'is_my_recipe', 'is_reviewed'));
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $recipe = Recipe::with(['ingredients', 'steps', 'reviews.user', 'user'])
            ->where('recipes.id', $id)
            ->first();
        if( !Auth::check() || (Auth::id() !== $recipe['user_id']) ) {
            abort(403);
        }
        $categories = Category::all();
        
        return view('recipes.edit', compact('recipe', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RecipeUpdateRequest $request, string $id)
    {
        $posts = $request->all();
        // 画像の分岐
        $update_array = [
            'title' => $posts['title'],
            'description' => $posts['description'],
            'category_id' => $posts['category_id'],
            'created_at' => now(),
            'updated_at' => now()
        ];
        if( $request->hasFile('image') ) {
            $image = $request->file('image');
            // s3に画像をアップロード
            $path = Storage::disk('s3')->putFile('recipe', $image, 'public');
            // s3のURLを取得
            $url = Storage::disk('s3')->url($path);
            // DBにはURLを保存
            $update_array['image'] = $url;
        }
        try {
            DB::beginTransaction();
            Recipe::where('id', $id)->update($update_array);
            Ingredient::where('recipe_id', $id)->delete();
            STEP::where('recipe_id', $id)->delete();
            $ingredients = [];
            foreach($posts['ingredients'] as $key => $ingredient){
                $ingredients[$key] = [
                    'recipe_id' => $id,
                    'name' => $ingredient['name'],
                    'quantity' => $ingredient['quantity'],
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            Ingredient::insert($ingredients);
            $steps = [];
            foreach($posts['steps'] as $key => $step){
                $steps[$key] = [
                    'recipe_id' => $id,
                    'step_number' => $key + 1,
                    'description' => $step,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            STEP::insert($steps);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::debug(print_r($th->getMessage(), true));
            throw $th;
        }
        flash()->success('レシピを更新しました！');

        return redirect()->route('recipe.show', ['id' => $id]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Recipe::where('id', $id)->delete();

        flash()->warning('レシピを削除しました！');
        return redirect()->route('home');
    }
}