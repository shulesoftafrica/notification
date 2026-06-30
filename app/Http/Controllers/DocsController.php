<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class DocsController extends Controller
{
    /**
     * Show the documentation home page
     */
    public function index()
    {
        return view('docs.index', [
            'title' => 'API Documentation',
            'version' => '1.1'
        ]);
    }

    /**
     * Show the getting started guide
     */
    public function gettingStarted()
    {
        return view('docs.guides.getting-started', [
            'title' => 'Getting Started'
        ]);
    }

    /**
     * Show the API reference
     */
    public function reference()
    {
        $markdown = File::get(base_path('docs/api-reference.md'));
        
        return view('docs.reference', [
            'title' => 'API Reference',
            'markdown' => $markdown
        ]);
    }

    /**
     * Show the interactive API explorer with Swagger UI
     */
    public function explorer()
    {
        return view('docs.explorer', [
            'title' => 'API Explorer'
        ]);
    }

    /**
     * Show webhook testing dashboard
     */
    public function webhooks()
    {
        return view('docs.webhooks', [
            'title' => 'Webhook Tester'
        ]);
    }

    /**
     * Show code examples
     */
    public function codeExamples()
    {
        return view('docs.code-examples', [
            'title' => 'Code Examples'
        ]);
    }

    /**
     * Show guides list or specific guide
     */
    public function guides($guide = null)
    {
        if (!$guide) {
            return view('docs.guides.index', [
                'title' => 'Developer Guides'
            ]);
        }

        $guidePath = base_path("resources/docs/guides/{$guide}.md");
        
        if (!File::exists($guidePath)) {
            abort(404);
        }

        $markdown = File::get($guidePath);
        
        return view('docs.guides.show', [
            'title' => ucfirst(str_replace('-', ' ', $guide)),
            'markdown' => $markdown,
            'guide' => $guide
        ]);
    }

    /**
     * Show changelog
     */
    public function changelog()
    {
        return view('docs.changelog', [
            'title' => 'Changelog'
        ]);
    }

    /**
     * Show status page
     */
    public function status()
    {
        return view('docs.status', [
            'title' => 'API Status'
        ]);
    }

    /**
     * Generate and return OpenAPI specification
     */
    public function openApiSpec()
    {
        $spec = $this->generateOpenAPISpec();
        
        return response()->json($spec, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Download Postman collection
     */
    public function postmanCollection()
    {
        $collection = $this->generatePostmanCollection();
        
        return response()->json($collection, 200, [
            'Content-Disposition' => 'attachment; filename="notification-api.postman_collection.json"'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate OpenAPI 3.0 specification from routes
     */
    private function generateOpenAPISpec()
    {
        $routes = Route::getRoutes();
        $paths = [];

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $methods = $route->methods();
                
                foreach ($methods as $method) {
                    if ($method !== 'HEAD') {
                        $path = '/' . $route->uri();
                        
                        if (!isset($paths[$path])) {
                            $paths[$path] = [];
                        }
                        
                        $paths[$path][strtolower($method)] = $this->generateOperationSpec($route, $method);
                    }
                }
            }
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Notification Service API',
                'description' => 'Multi-channel notification service supporting Email, SMS, and WhatsApp',
                'version' => '1.1',
                'contact' => [
                    'name' => 'API Support',
                ],
            ],
            'servers' => [
                [
                    'url' => url('/'),
                    'description' => 'Current Environment'
                ],
                [
                    'url' => 'http://localhost/notification',
                    'description' => 'Local Development'
                ],
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'API key for authentication (minimum 32 characters)'
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Bearer token authentication'
                    ]
                ],
                'schemas' => $this->getSchemaDefinitions()
            ],
            'security' => [
                ['ApiKeyAuth' => []]
            ]
        ];
    }

    /**
     * Generate operation specification for a route
     */
    private function generateOperationSpec($route, $method)
    {
        $uri = $route->uri();
        $action = $route->getActionName();
        
        // Extract summary from route URI
        $summary = ucfirst(str_replace('/', ' ', str_replace('api/', '', $uri)));
        
        return [
            'summary' => $summary,
            'operationId' => str_replace(['/', '{', '}'], ['_', '', ''], $uri) . '_' . strtolower($method),
            'tags' => [$this->getTagFromUri($uri)],
            'responses' => [
                '200' => [
                    'description' => 'Successful response'
                ],
                '401' => [
                    'description' => 'Unauthorized - Invalid or missing API key'
                ],
                '422' => [
                    'description' => 'Validation error'
                ],
                '500' => [
                    'description' => 'Internal server error'
                ]
            ]
        ];
    }

    /**
     * Get tag from URI
     */
    private function getTagFromUri($uri)
    {
        $parts = explode('/', str_replace('api/', '', $uri));
        return ucfirst($parts[0] ?? 'General');
    }

    /**
     * Get schema definitions for common objects
     */
    private function getSchemaDefinitions()
    {
        return [
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'error' => ['type' => 'string'],
                    'message' => ['type' => 'string']
                ]
            ],
            'ValidationError' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => 'object']
                ]
            ]
        ];
    }

    /**
     * Generate Postman Collection v2.1
     */
    private function generatePostmanCollection()
    {
        return [
            'info' => [
                'name' => 'Notification Service API',
                'description' => 'Multi-channel notification service supporting Email, SMS, and WhatsApp',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                'version' => '1.1'
            ],
            'auth' => [
                'type' => 'apikey',
                'apikey' => [
                    ['key' => 'key', 'value' => 'X-API-Key', 'type' => 'string'],
                    ['key' => 'value', 'value' => '{{API_KEY}}', 'type' => 'string'],
                    ['key' => 'in', 'value' => 'header', 'type' => 'string']
                ]
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => url('/api'),
                    'type' => 'string'
                ],
                [
                    'key' => 'API_KEY',
                    'value' => 'your_api_key_here_minimum_32_characters',
                    'type' => 'string'
                ],
                [
                    'key' => 'schema_name',
                    'value' => 'your_schema_name',
                    'type' => 'string'
                ]
            ],
            'item' => $this->getPostmanFolders()
        ];
    }

    /**
     * Get Postman collection folders/items
     */
    private function getPostmanFolders()
    {
        return [
            [
                'name' => 'Health Check',
                'item' => [
                    [
                        'name' => 'Get Health Status',
                        'request' => [
                            'method' => 'GET',
                            'header' => [],
                            'url' => [
                                'raw' => '{{baseUrl}}/health',
                                'host' => ['{{baseUrl}}'],
                                'path' => ['health']
                            ],
                            'description' => 'Check API health status'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Notifications',
                'item' => [
                    [
                        'name' => 'Send Single Notification',
                        'request' => [
                            'method' => 'POST',
                            'header' => [
                                ['key' => 'Content-Type', 'value' => 'application/json']
                            ],
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'schema_name' => '{{schema_name}}',
                                    'channel' => 'email',
                                    'to' => 'customer@example.com',
                                    'subject' => 'Test Email',
                                    'message' => 'This is a test message',
                                    'provider' => 'sendgrid',
                                    'priority' => 'normal'
                                ], JSON_PRETTY_PRINT)
                            ],
                            'url' => [
                                'raw' => '{{baseUrl}}/notifications/send',
                                'host' => ['{{baseUrl}}'],
                                'path' => ['notifications', 'send']
                            ]
                        ]
                    ],
                    [
                        'name' => 'Send Bulk Notifications',
                        'request' => [
                            'method' => 'POST',
                            'header' => [
                                ['key' => 'Content-Type', 'value' => 'application/json']
                            ],
                            'body' => [
                                'mode' => 'raw',
                                'raw' => json_encode([
                                    'schema_name' => '{{schema_name}}',
                                    'channel' => 'email',
                                    'messages' => [
                                        [
                                            'to' => 'user1@example.com',
                                            'subject' => 'Test',
                                            'message' => 'Message 1'
                                        ],
                                        [
                                            'to' => 'user2@example.com',
                                            'subject' => 'Test',
                                            'message' => 'Message 2'
                                        ]
                                    ]
                                ], JSON_PRETTY_PRINT)
                            ],
                            'url' => [
                                'raw' => '{{baseUrl}}/notifications/bulk/send',
                                'host' => ['{{baseUrl}}'],
                                'path' => ['notifications', 'bulk', 'send']
                            ]
                        ]
                    ],
                    [
                        'name' => 'Get Notification Status',
                        'request' => [
                            'method' => 'GET',
                            'url' => [
                                'raw' => '{{baseUrl}}/notifications/:id',
                                'host' => ['{{baseUrl}}'],
                                'path' => ['notifications', ':id'],
                                'variable' => [
                                    ['key' => 'id', 'value' => '1']
                                ]
                            ]
                        ]
                    ],
                    [
                        'name' => 'List Notifications',
                        'request' => [
                            'method' => 'GET',
                            'url' => [
                                'raw' => '{{baseUrl}}/notifications',
                                'host' => ['{{baseUrl}}'],
                                'path' => ['notifications']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
