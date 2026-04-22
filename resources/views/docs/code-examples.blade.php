@extends('docs.layout')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900 mb-4">
        <i class="fas fa-code text-blue-600 mr-3"></i>
        Code Examples
    </h1>
    <p class="text-lg text-gray-600 mb-8">
        Ready-to-use code snippets in multiple programming languages.
    </p>

    <!-- Language Selector -->
    <div class="mb-8 flex flex-wrap gap-2">
        <button class="language-tab active px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition" data-lang="curl">
            <i class="fas fa-terminal mr-2"></i>cURL
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="php">
            <i class="fab fa-php mr-2"></i>PHP
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="python">
            <i class="fab fa-python mr-2"></i>Python
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="javascript">
            <i class="fab fa-js mr-2"></i>JavaScript
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="ruby">
            <i class="fas fa-gem mr-2"></i>Ruby
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="go">
            <i class="fas fa-code mr-2"></i>Go
        </button>
        <button class="language-tab px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition" data-lang="java">
            <i class="fab fa-java mr-2"></i>Java
        </button>
    </div>

    <!-- Example 1: Send Email -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Send Email Notification</h2>
        
        <div class="code-example" data-lang="curl">
            <pre><code class="language-bash">curl -X POST {{ url('/api/notifications/send') }} \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{
    "schema_name": "my_app",
    "channel": "email",
    "to": "customer@example.com",
    "subject": "Welcome to Our Service",
    "message": "Thank you for signing up!",
    "provider": "sendgrid",
    "priority": "high"
  }'</code></pre>
        </div>

        <div class="code-example hidden" data-lang="php">
            <pre><code class="language-php">&lt;?php
$apiKey = 'your_api_key_here';
$baseUrl = '{{ url('/api') }}';

$data = [
    'schema_name' => 'my_app',
    'channel' => 'email',
    'to' => 'customer@example.com',
    'subject' => 'Welcome to Our Service',
    'message' => 'Thank you for signing up!',
    'provider' => 'sendgrid',
    'priority' => 'high'
];

$ch = curl_init($baseUrl . '/notifications/send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 201) {
    echo "Email sent! Message ID: " . $result['message_id'];
} else {
    echo "Error: " . $result['message'];
}</code></pre>
        </div>

        <div class="code-example hidden" data-lang="python">
            <pre><code class="language-python">import requests
import json

api_key = 'your_api_key_here'
base_url = '{{ url('/api') }}'

headers = {
    'Content-Type': 'application/json',
    'X-API-Key': api_key
}

data = {
    'schema_name': 'my_app',
    'channel': 'email',
    'to': 'customer@example.com',
    'subject': 'Welcome to Our Service',
    'message': 'Thank you for signing up!',
    'provider': 'sendgrid',
    'priority': 'high'
}

response = requests.post(
    f'{base_url}/notifications/send',
    headers=headers,
    json=data
)

if response.status_code == 201:
    result = response.json()
    print(f"Email sent! Message ID: {result['message_id']}")
else:
    print(f"Error: {response.json()['message']}")</code></pre>
        </div>

        <div class="code-example hidden" data-lang="javascript">
            <pre><code class="language-javascript">const apiKey = 'your_api_key_here';
const baseUrl = '{{ url('/api') }}';

const data = {
  schema_name: 'my_app',
  channel: 'email',
  to: 'customer@example.com',
  subject: 'Welcome to Our Service',
  message: 'Thank you for signing up!',
  provider: 'sendgrid',
  priority: 'high'
};

fetch(`${baseUrl}/notifications/send`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': apiKey
  },
  body: JSON.stringify(data)
})
  .then(response => response.json())
  .then(result => {
    if (result.success) {
      console.log(`Email sent! Message ID: ${result.message_id}`);
    } else {
      console.error(`Error: ${result.message}`);
    }
  })
  .catch(error => console.error('Error:', error));</code></pre>
        </div>

        <div class="code-example hidden" data-lang="ruby">
            <pre><code class="language-ruby">require 'net/http'
require 'json'

api_key = 'your_api_key_here'
base_url = '{{ url('/api') }}'
uri = URI("#{base_url}/notifications/send")

data = {
  schema_name: 'my_app',
  channel: 'email',
  to: 'customer@example.com',
  subject: 'Welcome to Our Service',
  message: 'Thank you for signing up!',
  provider: 'sendgrid',
  priority: 'high'
}

http = Net::HTTP.new(uri.host, uri.port)
http.use_ssl = uri.scheme == 'https'

request = Net::HTTP::Post.new(uri.path)
request['Content-Type'] = 'application/json'
request['X-API-Key'] = api_key
request.body = data.to_json

response = http.request(request)
result = JSON.parse(response.body)

if response.code == '201'
  puts "Email sent! Message ID: #{result['message_id']}"
else
  puts "Error: #{result['message']}"
end</code></pre>
        </div>

        <div class="code-example hidden" data-lang="go">
            <pre><code class="language-go">package main

import (
    "bytes"
    "encoding/json"
    "fmt"
    "io/ioutil"
    "net/http"
)

func main() {
    apiKey := "your_api_key_here"
    baseURL := "{{ url('/api') }}"

    data := map[string]interface{}{
        "schema_name": "my_app",
        "channel":     "email",
        "to":          "customer@example.com",
        "subject":     "Welcome to Our Service",
        "message":     "Thank you for signing up!",
        "provider":    "sendgrid",
        "priority":    "high",
    }

    jsonData, _ := json.Marshal(data)

    req, _ := http.NewRequest("POST", baseURL+"/notifications/send", bytes.NewBuffer(jsonData))
    req.Header.Set("Content-Type", "application/json")
    req.Header.Set("X-API-Key", apiKey)

    client := &http.Client{}
    resp, err := client.Do(req)
    if err != nil {
        panic(err)
    }
    defer resp.Body.Close()

    body, _ := ioutil.ReadAll(resp.Body)
    
    var result map[string]interface{}
    json.Unmarshal(body, &result)

    if resp.StatusCode == 201 {
        fmt.Printf("Email sent! Message ID: %.0f\\n", result["message_id"])
    } else {
        fmt.Printf("Error: %s\\n", result["message"])
    }
}</code></pre>
        </div>

        <div class="code-example hidden" data-lang="java">
            <pre><code class="language-java">import java.net.http.*;
import java.net.URI;
import org.json.JSONObject;

public class NotificationExample {
    public static void main(String[] args) throws Exception {
        String apiKey = "your_api_key_here";
        String baseUrl = "{{ url('/api') }}";

        JSONObject data = new JSONObject();
        data.put("schema_name", "my_app");
        data.put("channel", "email");
        data.put("to", "customer@example.com");
        data.put("subject", "Welcome to Our Service");
        data.put("message", "Thank you for signing up!");
        data.put("provider", "sendgrid");
        data.put("priority", "high");

        HttpClient client = HttpClient.newHttpClient();
        HttpRequest request = HttpRequest.newBuilder()
            .uri(URI.create(baseUrl + "/notifications/send"))
            .header("Content-Type", "application/json")
            .header("X-API-Key", apiKey)
            .POST(HttpRequest.BodyPublishers.ofString(data.toString()))
            .build();

        HttpResponse<String> response = client.send(request, 
            HttpResponse.BodyHandlers.ofString());

        JSONObject result = new JSONObject(response.body());

        if (response.statusCode() == 201) {
            System.out.println("Email sent! Message ID: " + result.getInt("message_id"));
        } else {
            System.out.println("Error: " + result.getString("message"));
        }
    }
}</code></pre>
        </div>
    </div>

    <!-- Example 2: Send SMS -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Send SMS Notification</h2>
        
        <div class="code-example" data-lang="curl">
            <pre><code class="language-bash">curl -X POST {{ url('/api/notifications/send') }} \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{
    "schema_name": "my_app",
    "channel": "sms",
    "to": "+255712345678",
    "message": "Your verification code is: 123456",
    "provider": "beem",
    "priority": "urgent"
  }'</code></pre>
        </div>

        <div class="code-example hidden" data-lang="php">
            <pre><code class="language-php">$data = [
    'schema_name' => 'my_app',
    'channel' => 'sms',
    'to' => '+255712345678',
    'message' => 'Your verification code is: 123456',
    'provider' => 'beem',
    'priority' => 'urgent'
];

// Use same request code as email example</code></pre>
        </div>

        <div class="code-example hidden" data-lang="python">
            <pre><code class="language-python">data = {
    'schema_name': 'my_app',
    'channel': 'sms',
    'to': '+255712345678',
    'message': 'Your verification code is: 123456',
    'provider': 'beem',
    'priority': 'urgent'
}

# Use same request code as email example</code></pre>
        </div>

        <div class="code-example hidden" data-lang="javascript">
            <pre><code class="language-javascript">const data = {
  schema_name: 'my_app',
  channel: 'sms',
  to: '+255712345678',
  message: 'Your verification code is: 123456',
  provider: 'beem',
  priority: 'urgent'
};

// Use same fetch code as email example</code></pre>
        </div>
    </div>

    <!-- Example 3: Bulk Send -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Send Bulk Notifications</h2>
        
        <div class="code-example" data-lang="curl">
            <pre><code class="language-bash">curl -X POST {{ url('/api/notifications/bulk/send') }} \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your_api_key_here" \
  -d '{
    "schema_name": "my_app",
    "channel": "email",
    "provider": "sendgrid",
    "priority": "normal",
    "rate_limit": 100,
    "messages": [
      {
        "to": "user1@example.com",
        "subject": "Welcome!",
        "message": "Hello User 1!"
      },
      {
        "to": "user2@example.com",
        "subject": "Welcome!",
        "message": "Hello User 2!"
      }
    ]
  }'</code></pre>
        </div>

        <div class="code-example hidden" data-lang="python">
            <pre><code class="language-python">data = {
    'schema_name': 'my_app',
    'channel': 'email',
    'provider': 'sendgrid',
    'priority': 'normal',
    'rate_limit': 100,
    'messages': [
        {
            'to': 'user1@example.com',
            'subject': 'Welcome!',
            'message': 'Hello User 1!'
        },
        {
            'to': 'user2@example.com',
            'subject': 'Welcome!',
            'message': 'Hello User 2!'
        }
    ]
}

response = requests.post(
    f'{base_url}/notifications/bulk/send',
    headers=headers,
    json=data
)</code></pre>
        </div>
    </div>

    <!-- Example 4: Check Status -->
    <div class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Check Notification Status</h2>
        
        <div class="code-example" data-lang="curl">
            <pre><code class="language-bash">curl -X GET {{ url('/api/notifications/123') }} \
  -H "X-API-Key: your_api_key_here"</code></pre>
        </div>

        <div class="code-example hidden" data-lang="php">
            <pre><code class="language-php">$messageId = 123;

$ch = curl_init($baseUrl . '/notifications/' . $messageId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
echo "Status: " . $result['data']['status'];</code></pre>
        </div>

        <div class="code-example hidden" data-lang="python">
            <pre><code class="language-python">message_id = 123

response = requests.get(
    f'{base_url}/notifications/{message_id}',
    headers={'X-API-Key': api_key}
)

result = response.json()
print(f"Status: {result['data']['status']}")</code></pre>
        </div>

        <div class="code-example hidden" data-lang="javascript">
            <pre><code class="language-javascript">const messageId = 123;

fetch(`${baseUrl}/notifications/${messageId}`, {
  headers: {
    'X-API-Key': apiKey
  }
})
  .then(response => response.json())
  .then(result => {
    console.log(`Status: ${result.data.status}`);
  });</code></pre>
        </div>
    </div>

    <!-- SDK Information -->
    <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-8">
        <h2 class="text-2xl font-semibold text-gray-900 mb-4">Need an SDK?</h2>
        <p class="text-gray-700 mb-6">
            While we don't currently provide official SDKs, the examples above show how easy it is to integrate with any HTTP client library.
            The API follows REST principles and returns standard JSON responses.
        </p>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <i class="fas fa-download text-blue-600 text-2xl mb-2"></i>
                <h4 class="font-semibold mb-1">Postman Collection</h4>
                <p class="text-sm text-gray-600 mb-3">Import ready-to-use requests</p>
                <a href="{{ route('docs.postman') }}" class="text-blue-600 hover:underline text-sm font-semibold">Download →</a>
            </div>
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <i class="fas fa-file-code text-blue-600 text-2xl mb-2"></i>
                <h4 class="font-semibold mb-1">OpenAPI Spec</h4>
                <p class="text-sm text-gray-600 mb-3">Generate your own SDK</p>
                <a href="{{ route('docs.openapi') }}" target="_blank" class="text-blue-600 hover:underline text-sm font-semibold">View Spec →</a>
            </div>
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <i class="fas fa-play-circle text-blue-600 text-2xl mb-2"></i>
                <h4 class="font-semibold mb-1">API Explorer</h4>
                <p class="text-sm text-gray-600 mb-3">Test in your browser</p>
                <a href="{{ route('docs.explorer') }}" class="text-blue-600 hover:underline text-sm font-semibold">Launch →</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra-js')
<script>
$(document).ready(function() {
    // Language tab switching
    $('.language-tab').click(function() {
        const lang = $(this).data('lang');
        
        // Update tab styles
        $('.language-tab').removeClass('active bg-blue-600 text-white').addClass('bg-gray-200 text-gray-700');
        $(this).removeClass('bg-gray-200 text-gray-700').addClass('active bg-blue-600 text-white');
        
        // Show/hide code examples
        $('.code-example').addClass('hidden');
        $(`.code-example[data-lang="${lang}"]`).removeClass('hidden');
    });
});
</script>
@endsection
