@extends('layouts.app')

@section('title', ucfirst($slug).' Cemetery — Teton County Cemetery Records')

@section('content')
<div class="text-center py-16">
    <h2 class="text-3xl font-bold text-gray-800 mb-2">
        {{ ucfirst($slug) }} Municipal Cemetery
    </h2>
    <p class="text-gray-500 text-lg mb-8">Teton County, Montana</p>
    <p class="text-gray-400 italic">Cemetery records application — coming soon.</p>
</div>
@endsection
