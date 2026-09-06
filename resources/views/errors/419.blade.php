@extends('errors::layout')

@section('code', '419')
@section('title', 'Your session expired')
@section('message', 'You were away long enough that we closed the page for safety. Sign in again and pick up where you left off.')
@section('actions')
    <a class="secondary" href="/login">Sign in</a>
@endsection
