@extends('frontend.layouts.app')
@push('seo')
<title>{{ $blog->title }} | {{env('APP_NAME','Wisselbanken')}}</title>
<meta name="description" content="{{ $blog->meta_description ?? Str::limit(strip_tags($blog->content), 160) }}">
@if($blog->meta_keywords)
<meta name="keywords" content="{{ $blog->meta_keywords }}">
@endif
@endpush
@push('css')
<style>
    .blog-detail-page {
        padding: 0;
    }
    
    .blog-banner {
        position: relative;
        width: 100%;
        height: 500px;
        overflow: hidden;
        background: linear-gradient(135deg, #4A171E 0%, #6b2a3a 100%);
    }
    
    .blog-banner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .blog-banner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.6) 100%);
        display: flex;
        align-items: center;
        padding: 40px;
    }
    
    .blog-banner-title {
        color: #fff;
        font-size: 48px;
        font-weight: bold;
        line-height: 1.2;
        text-transform: uppercase;
        text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
        max-width: 800px;
    }
    
    .breadcrumb-section {
        background: #fff;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
    }
    
    .breadcrumb {
        margin: 0;
        background: transparent;
        padding: 0;
    }
    
    .breadcrumb-item a {
        color: #4A171E;
        text-decoration: none;
    }
    
    .breadcrumb-item a:hover {
        color: #e3b143;
    }
    
    .breadcrumb-item.active {
        color: #666;
    }
    
    .blog-content-section {
        padding: 40px 0;
        background: #fff;
    }
    
    .blog-meta-bar {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
        flex-wrap: wrap;
    }
    
    .blog-category-tag {
        display: inline-block;
        background: #4A171E;
        color: #fff;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .blog-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 14px;
    }
    
    .blog-meta-item i {
        color: #999;
    }
    
    .blog-title {
        font-size: 36px;
        font-weight: bold;
        color: #333;
        margin-bottom: 20px;
        line-height: 1.3;
    }
    
    .blog-content {
        font-size: 16px;
        line-height: 1.8;
        color: #444;
    }
    
    .blog-content h2,
    .blog-content h3 {
        color: #333;
        margin-top: 30px;
        margin-bottom: 15px;
    }
    
    .blog-content p {
        margin-bottom: 20px;
    }
    
    .blog-content ul,
    .blog-content ol {
        margin-bottom: 20px;
        padding-left: 30px;
    }
    
    .blog-content li {
        margin-bottom: 10px;
    }
    
    .recent-blogs-section {
        padding: 60px 0;
        background: #f8f9fa;
    }
    
    .section-title {
        font-size: 28px;
        font-weight: bold;
        color: #333;
        margin-bottom: 30px;
    }
    
    .recent-blog-card {
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
        margin-bottom: 20px;
    }
    
    .recent-blog-card:hover {
        transform: translateY(-3px);
    }
    
    .recent-blog-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }
    
    .recent-blog-body {
        padding: 15px;
    }
    
    .recent-blog-title {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    .recent-blog-title a {
        color: #333;
        text-decoration: none;
    }
    
    .recent-blog-title a:hover {
        color: #4A171E;
    }
    
    .recent-blog-meta {
        font-size: 12px;
        color: #999;
    }
    
    @media (max-width: 768px) {
        .blog-banner-title {
            font-size: 32px;
        }
        
        .blog-title {
            font-size: 28px;
        }
        
        .blog-banner {
            height: 350px;
        }
    }
</style>
@endpush

@section('content')
<div class="blog-detail-page">
    <!-- Breadcrumb -->
    <div class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blogs.index') }}">Blogs</a></li>
                    <li class="breadcrumb-item active">{{ $blog->title }}</li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- Banner -->
    <div class="blog-banner">
        @if($blog->featured_image)
            <img src="{{ asset('storage/' . $blog->featured_image) }}" alt="{{ $blog->title }}">
        @endif
        <div class="blog-banner-overlay">
            <div class="container">
                <h1 class="blog-banner-title">{{ strtoupper($blog->title) }}</h1>
            </div>
        </div>
    </div>
    
    <!-- Content -->
    <div class="blog-content-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="blog-meta-bar">
                        <span class="blog-category-tag">Construction</span>
                        <span class="blog-meta-item">
                            <i class="fi-rs-user"></i>
                            <span>Admin</span>
                        </span>
                        <span class="blog-meta-item">
                            <i class="fi-rs-calendar"></i>
                            <span>{{ $blog->created_at->format('F d, Y') }}</span>
                        </span>
                    </div>
                    
                    <h2 class="blog-title">{{ $blog->title }}</h2>
                    
                    <div class="blog-content">
                        {!! $blog->content !!}
                    </div>
                </div>
                
                <div class="col-lg-4">
                    @if($recentBlogs->count() > 0)
                    <div class="recent-blogs-sidebar">
                        <h3 class="section-title">Recent Posts</h3>
                        @foreach($recentBlogs as $recentBlog)
                        <div class="recent-blog-card">
                            @if($recentBlog->featured_image)
                                <img src="{{ asset('storage/' . $recentBlog->featured_image) }}" alt="{{ $recentBlog->title }}" class="recent-blog-image">
                            @endif
                            <div class="recent-blog-body">
                                <h4 class="recent-blog-title">
                                    <a href="{{ route('blogs.show', $recentBlog->slug) }}">{{ $recentBlog->title }}</a>
                                </h4>
                                <div class="recent-blog-meta">
                                    {{ $recentBlog->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

