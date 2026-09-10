<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\BlogDataTable;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(BlogDataTable $dataTable)
    {
        return $dataTable->render('admin.blogs.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'status' => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->title);
        
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Blog::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        }

        Blog::create($data);

        return response()->json(['message' => 'Blog created successfully!']);
    }

    public function edit($id)
    {
        $blog = Blog::findOrFail($id);
        return $blog;
    }

    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
            'status' => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->title);
        
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Blog::where('slug', $data['slug'])->where('id', '!=', $blog->id)->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        if ($request->hasFile('featured_image')) {
            if ($blog->featured_image) {
                Storage::disk('public')->delete($blog->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('blogs', 'public');
        }

        $blog->update($data);

        return response()->json(['message' => 'Blog updated successfully!']);
    }

    public function destroy($id)
    {
        $blog = Blog::findOrFail($id);
        
        if ($blog->featured_image) {
            Storage::disk('public')->delete($blog->featured_image);
        }
        
        if (!$blog->delete()) {
            return response()->json(['status' => 'failure', 'message' => 'Something went wrong!']);
        }
        
        return response()->json(['status' => 'success', 'message' => 'Blog deleted successfully.']);
    }
}
