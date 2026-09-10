@extends('frontend.layouts.app')

@push('seo')
<title>My Workspace - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endpush

@push('css')
<style>
    .workspace-page { padding: 40px 0 60px; }
    .workspace-header {
        margin-bottom: 32px;
        padding-bottom: 16px;
        border-bottom: 1px solid #efe2d7;
    }
    .workspace-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #4a171e;
        margin-bottom: 4px;
    }
    .workspace-header p {
        color: #8a6f73;
        margin: 0;
    }
    .workspace-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 20px;
        width: 100%;
    }
    .workspace-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 140px;
        padding: 24px 16px;
        background: #fff;
        border: 1px solid #efe2d7;
        border-radius: 12px;
        text-decoration: none;
        color: #4a171e;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(74, 23, 30, 0.06);
    }
    .workspace-tile:hover {
        border-color: #e3b143;
        box-shadow: 0 8px 24px rgba(74, 23, 30, 0.12);
        color: #4a171e;
        transform: translateY(-2px);
    }
    .workspace-tile .tile-icon {
        font-size: 32px;
        margin-bottom: 12px;
        opacity: 0.9;
    }
    .workspace-tile .tile-label {
        font-weight: 600;
        font-size: 15px;
        text-align: center;
    }
    .workspace-tile.workspace-tile-logout {
        border-color: rgba(220, 53, 69, 0.3);
        color: #b12b2b;
    }
    .workspace-tile.workspace-tile-logout:hover {
        border-color: #dc3545;
        background: #fff5f5;
        color: #b12b2b;
    }
    @media (max-width: 576px) {
        .workspace-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .workspace-tile { min-height: 120px; padding: 16px; }
        .workspace-tile .tile-icon { font-size: 28px; }
        .workspace-tile .tile-label { font-size: 13px; }
    }
</style>
@endpush

@section('content')
<main class="main">
    <div class="page-header breadcrumb-wrap">
        <div class="container-fluid px-4">
            <div class="breadcrumb">
                <a href="{{ url('/') }}" rel="nofollow">Home</a>
                <span></span>
                <span>My Workspace</span>
            </div>
        </div>
    </div>
    <section class="workspace-page">
        <div class="container-fluid px-4">
            <div class="workspace-header">
                <h1>{{ Auth::user()->name }}</h1>
                <p>Choose where you want to go</p>
            </div>
            <div class="workspace-grid">
                <a href="{{ url('product-filter') }}" class="workspace-tile">
                    <span class="tile-icon fi-rs-shop"></span>
                    <span class="tile-label">Products</span>
                </a>
                <a href="{{ url('view-lists') }}" class="workspace-tile">
                    <span class="tile-icon fi-rs-list-check"></span>
                    <span class="tile-label">View Lists</span>
                </a>
                <a href="{{ route('quotes.index') }}" class="workspace-tile">
                    <span class="tile-icon fi-rs-calculator"></span>
                    <span class="tile-label">Estimates</span>
                </a>
                <a href="{{ url('view-orders') }}" class="workspace-tile">
                    <span class="tile-icon fi-rs-box"></span>
                    <span class="tile-label">View Orders</span>
                </a>
                <a href="{{ url('profile') }}" class="workspace-tile">
                    <span class="tile-icon fi-rs-user"></span>
                    <span class="tile-label">Profile</span>
                </a>
                <a href="{{ route('logout') }}" class="workspace-tile workspace-tile-logout"
                   onclick="event.preventDefault(); document.getElementById('workspace-logout-form').submit();">
                    <span class="tile-icon fi-rs-sign-out"></span>
                    <span class="tile-label">Logout</span>
                </a>
            </div>
            <form id="workspace-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </div>
    </section>
</main>
@endsection
