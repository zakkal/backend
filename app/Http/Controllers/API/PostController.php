<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PostController extends Controller
{
   public function store(Request $request)
{
    $request->validate([
        'caption' => 'nullable|string',
        'image'   => 'required|image|mimes:jpg,png,jpeg|max:2048', // Max 2MB
    ]);

    $imageName = null;
    if ($request->hasFile('image')) {
        // Simpan file ke storage/app/public/posts
        $imageName = time() . '.' . $request->image->extension();
        $request->image->storeAs('public/posts', $imageName);
    }

    $post = Post::create([
        'user_id'   => auth()->id(),
        'caption'   => $request->caption,
        'image_url' => $imageName,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Post berhasil dibuat!',
        'data'    => $post->load('user')
    ], 201);
} //
}
