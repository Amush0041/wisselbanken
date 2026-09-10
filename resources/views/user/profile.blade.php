@extends('user.layouts.app')

@section('seo')
<title>Update Profile | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
@php
    $roleValue = Auth::user()->role ? ucfirst((string) Auth::user()->role) : '';
    // Users table currently doesn't have phone, but this will gracefully fall back to empty.
    $phoneValue = Auth::user()->phone_number ?? Auth::user()->phone ?? '';
@endphp
<div class="container-fluid flex-grow-1 container-p-y user-page user-profile">
    <div class="mb-3">
        <h4 class="page-title mb-1">Profile</h4>
        <p class="page-description mb-0">Manage your personal information and profile image.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card profile-card h-100">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        @if(Auth::user()->profile_image)
                            <img src="{{ asset('storage/' . Auth::user()->profile_image) }}" alt="Profile Image">
                        @else
                            <img src="{{ asset('default-avatar.jpg') }}" alt="Default Image">
                        @endif
                    </div>
                    <h5 class="mb-1">{{ Auth::user()->name }}</h5>
                    <p class="text-muted mb-3">{{ Auth::user()->email }}</p>
                    <div class="profile-meta">
                        <div class="meta-item">
                            <span class="meta-label">Role</span>
                            <span class="meta-value">{{ ucfirst(Auth::user()->role) }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Member Since</span>
                            <span class="meta-value">{{ Auth::user()->created_at?->format('M Y') }}</span>
                        </div>
                    </div>
                    <div class="mt-3 text-muted small">
                        Update your profile image and details on the right.
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card profile-form-card h-100">
                <div class="card-header py-3">
                    <h5 class="mb-0">Account Details</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', Auth::user()->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', Auth::user()->email) }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Role</label>
                                <input
                                    type="text"
                                    id="role"
                                    class="form-control"
                                    value="{{ $roleValue !== '' ? $roleValue : 'Role not set' }}"
                                    disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input
                                    type="text"
                                    id="phone_number"
                                    class="form-control"
                                    value="{{ $phoneValue !== '' ? $phoneValue : 'Phone number not set' }}"
                                    disabled>
                            </div>
                            <div class="col-12">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" name="profile_image" class="form-control">
                                <small class="text-muted d-block mt-1">JPG or PNG, max 2MB.</small>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="{{ url('user-dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
