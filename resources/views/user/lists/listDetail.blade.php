@extends('user.layouts.app')

@section('seo')
<title>{{ $list->name ?? '' }} - {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y user-page user-list-detail">
    @include('user.partials.frontend-compat')
    @include('frontend.lists.listDetail')
</div>
@endsection
