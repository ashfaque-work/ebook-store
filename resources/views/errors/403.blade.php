@extends('errors::layout')

@section('code', '403')
@section('title', 'That page isn't yours to open')
@section('message', 'You're signed in, but this area belongs to someone else. If you think that's wrong, get in touch.')
@section('actions')
    <a class="secondary" href="/library">Go to my library</a>
@endsection
