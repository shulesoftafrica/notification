<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProviderConfig;
use App\Services\Adapters\EmailAdapter;
use App\Services\Adapters\SmsAdapter;
use App\Services\Adapters\WhatsAppAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConfigController extends Controller
{
    /**
     * Get provider configurations for tenant
     */
    public function getProviders(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $configs = ProviderConfig::where('project_id', $project->project_id)
                                ->where('tenant_id', $tenantId)
                                ->get()
                                ->map(function ($config) {
                                    // Hide sensitive credentials
                                    $configArray = $config->toArray();
                                    $configArray['credentials'] = array_map(function ($value) {
                                        return is_string($value) ? '***' : $value;
                                    }, $config->credentials ?? []);
                                    return $configArray;
                                });

        return response()->json([
            'data' => $configs,
            'meta' => [
                'project_id' => $project->project_id,
                'tenant_id' => $tenantId,
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Update provider configurations
     */
    public function updateProviders(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'providers' => 'required|array',
            'providers.*.channel' => 'required|in:email,sms,whatsapp',
            'providers.*.provider' => 'required|string',
            'providers.*.priority' => 'required|integer|min:1|max:10',
            'providers.*.enabled' => 'required|boolean',
            'providers.*.credentials' => 'required|array',
            'providers.*.settings' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        $updatedConfigs = [];
        $errors = [];

        foreach ($request->input('providers') as $index => $providerData) {
            try {
                // Validate provider configuration
                $adapter = $this->getAdapter($providerData['channel']);
                if (!$adapter->validateConfig($providerData)) {
                    $errors["providers.{$index}"] = "Invalid configuration for {$providerData['provider']}";
                    continue;
                }

                // Find or create provider config
                $config = ProviderConfig::updateOrCreate(
                    [
                        'project_id' => $project->project_id,
                        'tenant_id' => $tenantId,
                        'channel' => $providerData['channel'],
                        'provider' => $providerData['provider']
                    ],
                    [
                        'config_id' => 'cfg_' . Str::ulid(),
                        'priority' => $providerData['priority'],
                        'enabled' => $providerData['enabled'],
                        'credentials' => $providerData['credentials'],
                        'settings' => $providerData['settings'] ?? []
                    ]
                );

                $updatedConfigs[] = $config;
            } catch (\Exception $e) {
                $errors["providers.{$index}"] = "Failed to update provider: " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'error' => [
                    'code' => 'PROVIDER_CONFIG_ERROR',
                    'message' => 'Some provider configurations failed to update.',
                    'details' => $errors,
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        return response()->json([
            'data' => $updatedConfigs,
            'meta' => [
                'updated_count' => count($updatedConfigs),
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Test provider configuration
     */
    public function testProvider(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        $validator = Validator::make($request->all(), [
            'channel' => 'required|in:email,sms,whatsapp',
            'provider' => 'required|string',
            'credentials' => 'required|array',
            'test_recipient' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors(),
                    'trace_id' => $requestId
                ]
            ], 422);
        }

        try {
            $adapter = $this->getAdapter($request->input('channel'));
            
            // Validate configuration
            if (!$adapter->validateConfig([
                'provider' => $request->input('provider'),
                'credentials' => $request->input('credentials')
            ])) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_PROVIDER_CONFIG',
                        'message' => 'Provider configuration is invalid.',
                        'trace_id' => $requestId
                    ]
                ], 422);
            }

            // Create temporary config for testing
            $tempConfig = new ProviderConfig([
                'provider' => $request->input('provider'),
                'credentials' => $request->input('credentials'),
                'settings' => $request->input('settings', [])
            ]);

            // Create test message (not saved to database)
            $testMessage = (object) [
                'message_id' => 'test_' . uniqid(),
                'recipient' => $request->input('test_recipient'),
                'channel' => $request->input('channel')
            ];

            // Create test content
            $testContent = [
                'subject' => 'Test Message from Notification Service',
                'content' => 'This is a test message to verify your provider configuration.',
                'html_content' => '<h1>Test Message</h1><p>This is a test message to verify your provider configuration.</p>'
            ];

            // Attempt to send test message
            $result = $adapter->send($testMessage, $testContent, $tempConfig);

            if ($result->isSuccess()) {
                return response()->json([
                    'data' => [
                        'test_result' => 'success',
                        'provider_message_id' => $result->getProviderMessageId(),
                        'cost' => $result->getCost(),
                        'message' => 'Test message sent successfully'
                    ],
                    'meta' => [
                        'trace_id' => $requestId
                    ]
                ]);
            } else {
                return response()->json([
                    'error' => [
                        'code' => 'PROVIDER_TEST_FAILED',
                        'message' => 'Provider test failed: ' . $result->getError(),
                        'trace_id' => $requestId
                    ]
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'PROVIDER_TEST_ERROR',
                    'message' => 'Provider test error: ' . $e->getMessage(),
                    'trace_id' => $requestId
                ]
            ], 500);
        }
    }

    /**
     * Get quotas and usage for tenant
     */
    public function getQuotas(Request $request): JsonResponse
    {
        $project = $request->input('authenticated_project');
        $tenantId = $request->input('authenticated_tenant_id');
        $requestId = $request->input('request_id');

        // Get current usage from messages table
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $dailyUsage = \DB::table('messages')
            ->where('project_id', $project->project_id)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        $monthlyUsage = \DB::table('messages')
            ->where('project_id', $project->project_id)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thisMonth)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        // Get project limits (you might want to store these in a separate table)
        $quotas = [
            'email' => ['daily' => 10000, 'monthly' => 300000],
            'sms' => ['daily' => 1000, 'monthly' => 30000],
            'whatsapp' => ['daily' => 500, 'monthly' => 15000]
        ];

        return response()->json([
            'data' => [
                'quotas' => $quotas,
                'usage' => [
                    'daily' => $dailyUsage,
                    'monthly' => $monthlyUsage
                ],
                'remaining' => [
                    'daily' => array_map(function ($channel) use ($quotas, $dailyUsage) {
                        return $quotas[$channel]['daily'] - ($dailyUsage[$channel] ?? 0);
                    }, array_keys($quotas)),
                    'monthly' => array_map(function ($channel) use ($quotas, $monthlyUsage) {
                        return $quotas[$channel]['monthly'] - ($monthlyUsage[$channel] ?? 0);
                    }, array_keys($quotas))
                ]
            ],
            'meta' => [
                'period' => [
                    'daily_start' => $today->toISOString(),
                    'monthly_start' => $thisMonth->toISOString()
                ],
                'trace_id' => $requestId
            ]
        ]);
    }

    /**
     * Get appropriate adapter for channel
     */
    private function getAdapter(string $channel)
    {
        return match($channel) {
            'email' => app(EmailAdapter::class),
            'sms' => app(SmsAdapter::class),
            'whatsapp' => app(WhatsAppAdapter::class),
            default => throw new \Exception('Unsupported channel: ' . $channel)
        };
    }
}
