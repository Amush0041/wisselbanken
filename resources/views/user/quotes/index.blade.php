@extends('user.layouts.app')

@section('seo')
<title>Estimates | {{ env('APP_NAME', 'Wisselbanken') }}</title>
@endsection

@section('content')
<div class="container-fluid flex-grow-1 user-page user-quotes user-quotes-fluid">
    @include('user.partials.frontend-compat')
    @include('frontend.quotes.index')
</div>
@endsection
