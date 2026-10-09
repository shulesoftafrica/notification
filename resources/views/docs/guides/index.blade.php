@extends('docs.layout')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-book text-blue-600 mr-3"></i>
        Developer Guides
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Step-by-step guides for working with the Notification Service API.
    </p>

    @php
        $guides = [
            ['slug' => 'authentication', 'title' => 'Authentication', 'summary' => 'How API keys are issued and sent with each request.'],
            ['slug' => 'sending-notifications', 'title' => 'Sending Notifications', 'summary' => 'Send SMS, email and WhatsApp messages.'],
            ['slug' => 'bulk-operations', 'title' => 'Bulk Operations', 'summary' => 'Send to many recipients efficiently.'],
            ['slug' => 'error-handling', 'title' => 'Error Handling', 'summary' => 'Understand error responses and retries.'],
            ['slug' => 'rate-limits', 'title' => 'Rate Limits', 'summary' => 'Limits per provider and how throttling works.'],
        ];
    @endphp

    <div class="mb-6">
        <a href="{{ route('docs.getting-started') }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-400">
            <h2 class="text-xl font-semibold text-gray-900">Getting Started</h2>
            <p class="text-gray-600 mt-1">Get up and running in minutes.</p>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($guides as $guide)
            @if (file_exists(resource_path('docs/guides/' . $guide['slug'] . '.md')))
                <a href="{{ route('docs.guides', $guide['slug']) }}" class="block bg-white p-6 rounded-lg border border-gray-200 hover:border-blue-400">
                    <h2 class="text-xl font-semibold text-gray-900">{{ $guide['title'] }}</h2>
                    <p class="text-gray-600 mt-1">{{ $guide['summary'] }}</p>
                </a>
            @else
                <div class="block bg-gray-50 p-6 rounded-lg border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-500">{{ $guide['title'] }}</h2>
                    <p class="text-gray-500 mt-1">{{ $guide['summary'] }}</p>
                    <p class="text-xs text-gray-400 mt-2">Coming soon</p>
                </div>
            @endif
        @endforeach
    </div>
</div>
@endsection
