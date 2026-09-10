@push('css')
<style>
    .profile-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #ccc;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #print-section, #print-section * {
            visibility: visible;
        }

        #print-section {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .no-print {
            display: none !important;
        }

        header, footer, nav, aside {
            display: none !important;
        }
    }
</style>
@endpush

<main class="main">
    <section class="mt-50 mb-50" id="print-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <h2 class="mb-4">Update Profile</h2>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-3 text-center">
                            @if(Auth::user()->profile_image)
                                <img src="{{ asset('storage/' . Auth::user()->profile_image) }}" class="profile-image" alt="Profile Image">
                            @else
                                <img src="{{ asset('default-avatar.jpg') }}" class="profile-image" alt="Default Image">
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', Auth::user()->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', Auth::user()->email) }}" readonly>
                        </div>
<!-- 
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password <small>(Leave blank to keep current)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div> -->

                        <div class="mb-3">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <input type="file" name="profile_image" class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
