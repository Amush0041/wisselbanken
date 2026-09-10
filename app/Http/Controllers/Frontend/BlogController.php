<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::where('status', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('frontend.blogs.index', compact('blogs'));
    }

    public function show($slug)
    {
        $blog = Blog::where('slug', $slug)
            ->where('status', 1)
            ->firstOrFail();
        
        $blog->increment('views');
        
        $recentBlogs = Blog::where('status', 1)
            ->where('id', '!=', $blog->id)
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get();
        
        return view('frontend.blogs.show', compact('blog', 'recentBlogs'));
    }
}
