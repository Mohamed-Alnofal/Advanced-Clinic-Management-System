@extends('laboratory::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('laboratory.name') !!}</p>
@endsection
