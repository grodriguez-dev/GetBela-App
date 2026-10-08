<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::all();
        return response()->json(['banners' => $banners]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'link' => 'nullable|url|max:255',
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'image' => '/storage/' . $path,
            'link' => $request->link,
        ]);

        return response()->json(['banner' => $banner], 201);
    }

    public function show(Banner $banner)
    {
        return response()->json(['banner' => $banner]);
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'link' => 'nullable|url|max:255',
        ]);

        $data = $request->only(['link']);

        if ($request->hasFile('image')) {
            if ($banner->image && str_contains($banner->image, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $banner->image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('banners', 'public');
            $data['image'] = '/storage/' . $path;
        }

        $banner->update($data);
        return response()->json(['banner' => $banner]);
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image && str_contains($banner->image, '/storage/')) {
            $oldPath = str_replace('/storage/', '', $banner->image);
            Storage::disk('public')->delete($oldPath);
        }
        $banner->delete();
        return response()->json(['message' => 'Banner eliminado']);
    }
}
