@extends('admin.layouts.app')
@section('seo')
<title>Dashboard | {{env('APP_NAME',"Wisselbanken")}} </title>
<meta name="description" content="" />
@endsection
@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    @if(session('error'))
    <div class="alert alert-danger" role="alert">
        <div class="alert-body">
            <strong>{{ session('error') }}</strong>
            @if(session('error_details'))
            <ul class="mt-2 mb-0" style="padding-left: 20px;">
                @foreach(session('error_details') as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success" role="alert">
        <div class="alert-body">
            {{ session('success') }}
        </div>
    </div>
    @endif

    <div class="row g-6">
       
        <div class="col-lg-12 col-md-12">
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                      <h5 class="card-title mb-0">Statistics</h5> 
                    </div>
                    <div class="card-body">
                      <div class="row gy-3">
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-primary me-4 p-2">
                              <i class="ti ti-components ti-lg"></i>
                            </div>
                            <div class="card-info">
                              <h5 class="mb-0">{{$divisions ?? 0}}</h5>
                              <small>Divisions</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-info me-4 p-2"><i class="ti ti-category-plus ti-lg"></i></div>
                            <div class="card-info">
                              <h5 class="mb-0">{{$specifications ?? 0}}</h5>
                              <small>Specifications</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-success me-4 p-2">
                              <i class="ti ti-users ti-lg"></i>
                            </div>
                            <div class="card-info">
                              <h5 class="mb-0">{{$manufacturers ?? 0}}</h5>
                              <small>Manufacturer</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-danger me-4 p-2">
                              <i class="ti ti-shopping-cart ti-lg"></i>
                            </div>
                            <div class="card-info">
                              <h5 class="mb-0">{{$products ?? 0}}</h5>
                              <small>Products</small>
                            </div>
                          </div>
                        </div>
                      </div>

                      <hr class="my-3">
                      <p class="text-muted small fw-semibold text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">RBAC</p>
                      <div class="row gy-3">
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-primary me-4 p-2">
                              <i class="ti ti-building ti-lg"></i>
                            </div>
                            <div class="card-info">
                              <h5 class="mb-0">{{ $rbacOrgs ?? 0 }}</h5>
                              <small>Organizations</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3 col-6">
                          <div class="d-flex align-items-center">
                            <div class="badge rounded bg-label-success me-4 p-2">
                              <i class="ti ti-users ti-lg"></i>
                            </div>
                            <div class="card-info">
                              <h5 class="mb-0">{{ $rbacAssignments ?? 0 }}</h5>
                              <small>Active Assignments</small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
    </div>
</div>
@endsection