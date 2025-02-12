<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\PostgresSchemaState;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $id)
    {
        $posts = $request->all();
        // dd($id,$posts);
        Review::insert([
            'recipe_id' => $id,
            'user_id' => Auth::id(),
            'rating' => $posts['rating'],
            'comment' => $posts['comment'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        flash('レビューを投稿しました')->success();

        return redirect()->route('recipe.show', ['id' => $id]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
