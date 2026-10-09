@extends('docs.layout')

@section('content')
<div class="max-w-4xl mx-auto">
    <p class="text-sm text-gray-500 mb-4">
        <a href="{{ route('docs.guides') }}" class="hover:text-blue-600">Guides</a> / {{ $title }}
    </p>
    <div class="prose max-w-none bg-white p-8 rounded-lg border border-gray-200">
        {!! \Illuminate\Support\Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
    </div>
</div>
@endsection
