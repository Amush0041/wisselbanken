@extends('frontend.layouts.app')
@push('seo')
<title>Blogs | {{env('APP_NAME','Wisselbanken')}}</title>
<meta name="description" content="Read our latest blog posts about construction materials, specifications, and industry insights.">
@endpush
@push('css')
<style>
    .blog-listing-page {
        padding: 20px 0;
        background-color: #f8f9fa;
    }
    
    .blog-listing-page .container {
        max-width: 100%;
        padding-left: 5px;
        padding-right: 5px;
    }
    
    @media (min-width: 1200px) {
        .blog-listing-page .container {
            padding-left: 15px;
            padding-right: 15px;
        }
    }
    
    .blog-card {
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 20px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .blog-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    
    .blog-card-image {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        background: #e0e0e0;
    }
    
    .blog-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .blog-card:hover .blog-card-image img {
        transform: scale(1.05);
    }
    
    .blog-card-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.7) 100%);
        display: flex;
        align-items: flex-end;
        padding: 15px;
    }
    
    .blog-card-title-overlay {
        color: #fff;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        line-height: 1.2;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
    }
    
    .blog-card-body {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    
    .blog-card-category {
        display: inline-block;
        background: #4A171E;
        color: #fff;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    
    .blog-card-title {
        font-size: 16px;
        font-weight: bold;
        color: #333;
        margin-bottom: 8px;
        line-height: 1.3;
    }
    
    .blog-card-title a {
        color: #333;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .blog-card-title a:hover {
        color: #4A171E;
    }
    
    .blog-card-excerpt {
        color: #666;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 10px;
        flex-grow: 1;
    }
    
    .blog-card-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 12px;
        color: #999;
        padding-top: 10px;
        border-top: 1px solid #eee;
    }
    
    .blog-card-meta i {
        margin-right: 4px;
    }
    
    .pagination-wrapper {
        margin-top: 30px;
        display: flex;
        justify-content: center;
    }
    
    .no-image-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #4A171E 0%, #6b2a3a 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 18px;
        font-weight: bold;
        text-align: center;
        padding: 15px;
    }
    
    @media (min-width: 1400px) {
        .blog-card-col {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }
    
    @media (min-width: 1200px) and (max-width: 1399px) {
        .blog-card-col {
            flex: 0 0 25%;
            max-width: 25%;
        }
    }
    
    @media (min-width: 992px) and (max-width: 1199px) {
        .blog-card-col {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
    }
    
    @media (min-width: 768px) and (max-width: 991px) {
        .blog-card-col {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }
    
    @media (max-width: 767px) {
        .blog-card-col {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>
@endpush

@section('content')
<div class="blog-listing-page">
    <div class="container">
        <div class="row g-3">
            @forelse($blogs as $blog)
            <div class="col-lg-4 col-md-6 blog-card-col">
                <div class="blog-card">
                    <div class="blog-card-image">
                        @if($blog->featured_image)
                            <img src="{{ asset('storage/' . $blog->featured_image) }}" alt="{{ $blog->title }}">
                        @else
                            <div class="no-image-placeholder">
                                {{ strtoupper(substr($blog->title, 0, 30)) }}
                            </div>
                        @endif
                        <div class="blog-card-overlay">
                            <div class="blog-card-title-overlay">
                                {{ strtoupper($blog->title) }}
                            </div>
                        </div>
                    </div>
                    <div class="blog-card-body">
                        <span class="blog-card-category">Construction</span>
                        <h3 class="blog-card-title">
                            <a href="{{ route('blogs.show', $blog->slug) }}">{{ $blog->title }}</a>
                        </h3>
                        <p class="blog-card-excerpt">
                            {{ Str::limit(strip_tags($blog->content), 150) }}
                        </p>
                        <div class="blog-card-meta">
                            <span><i class="fi-rs-user"></i> Admin</span>
                            <span><i class="fi-rs-calendar"></i> {{ $blog->created_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5">
                    <p class="text-muted">No blog posts available yet.</p>
                </div>
            </div>
            @endforelse
        </div>
        
        @if($blogs->hasPages())
        <div class="pagination-wrapper">
            {{ $blogs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

