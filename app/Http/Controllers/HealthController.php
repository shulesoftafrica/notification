<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ProviderHealthService;
use App\Services\MetricsService;

class HealthController extends Controller
{
    protected $healthService;
    protected $metricsService;

    public function __construct(ProviderHealthService $healthService, MetricsService $metricsService)
    {
        $this->healthService = $healthService;
        $this->metricsService = $metricsService;
    }

    /**
     * Basic health check endpoint
     */
    public function check()
    {
        try {
            // Check database connection
            $dbHealth = $this->checkDatabase();

            // Check cache
            $cacheHealth = $this->checkCache();

            // Get overall health
            $overallHealth = $dbHealth && $cacheHealth;

            return response()->json([
                'status' => $overallHealth ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => $dbHealth,
                    'cache' => $cacheHealth,
                ],
                'uptime' => $this->getUptime(),
            ], $overallHealth ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Detailed health check with all services
     */
    public function detailed()
    {
        try {
            $startTime = microtime(true);

            // Basic health checks
            $dbHealth = $this->checkDatabase();
            $cacheHealth = $this->checkCache();

            // Provider health checks
            $providerHealth = $this->healthService->checkAllProviders();

            // Get metrics summary
            $metrics = $this->metricsService->getMetricsSummary('1h');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $overallHealth = $dbHealth && $cacheHealth &&
                collect($providerHealth)->where('healthy', true)->count() > 0;

            return response()->json([
                'status' => $overallHealth ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'response_time_ms' => $responseTime,
                'checks' => [
                    'database' => $dbHealth,
                    'cache' => $cacheHealth,
                    'providers' => $providerHealth,
                ],
                'metrics' => $metrics,
                'uptime' => $this->getUptime(),
                'environment' => app()->environment(),
                'version' => config('app.version', '1.0.0'),
            ], $overallHealth ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Check database connectivity
     */
    protected function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check cache connectivity
     */
    protected function checkCache()
    {
        try {
            cache()->put('health_check', true, 10);
            $result = cache()->get('health_check');
            cache()->forget('health_check');
            return $result === true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get application uptime
     */
    protected function getUptime()
    {
        try {
            $uptimeFile = storage_path('framework/cache/uptime');

            if (!file_exists($uptimeFile)) {
                file_put_contents($uptimeFile, now()->timestamp);
            }

            $startTime = file_get_contents($uptimeFile);
            return now()->timestamp - $startTime;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Provider-specific health check
     */
    public function provider($provider)
    {
        try {
            $health = $this->healthService->checkProvider($provider);

            return response()->json([
                'provider' => $provider,
                'health' => $health,
                'timestamp' => now()->toISOString(),
            ], $health['healthy'] ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'provider' => $provider,
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Refresh provider health cache
     */
    public function refreshProvider($provider)
    {
        try {
            $health = $this->healthService->refreshProvider($provider);

            return response()->json([
                'provider' => $provider,
                'health' => $health,
                'refreshed_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'provider' => $provider,
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system metrics
     */
    public function metrics(Request $request)
    {
        $period = $request->get('period', '1h');

        try {
            $metrics = $this->metricsService->getMetricsSummary($period);

            return response()->json([
                'metrics' => $metrics,
                'period' => $period,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Route any health check by channel name.
     */
    public function checkByChannel(Request $request)
    {
        try {
            $request->validate([
                'schema_name' => 'required|string',
                'channel' => 'required|string',
            ]);

            $channel = strtolower(trim($request->input('channel')));
            $methodMap = [
                'whatsapp' => 'checkWhatsApp',
                'sms' => 'checkSms',
                'phone-sms' => 'checkPhoneSms',
                'phone_sms' => 'checkPhoneSms',
            ];

            if (!array_key_exists($channel, $methodMap)) {
                return response()->json([
                    'channel' => $channel,
                    'status' => 'inactive',
                    'error' => 'Unsupported channel for health check.',
                ], 422);
            }

            return $this->{$methodMap[$channel]}($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'inactive',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'inactive',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check WhatsApp (WaSender) health for a given schema
     */
    public function checkWhatsApp(Request $request)
    {
        try {
            $request->validate([
                'schema_name' => 'required|string',
            ]);

            $schemaName = $request->input('schema_name');
            $session = \App\Models\WaSenderSession::where('schema_name', $schemaName)->first();

            if (!$session || empty($session->api_key)) {
                return response()->json([
                    'channel' => 'whatsapp',
                    'schema_name' => $schemaName,
                    'status' => 'inactive',
                    'message' => 'WaSender session is not configured with an API key.',
                ], 503);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://www.wasenderapi.com/api/status',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $session->api_key,
                    'Accept: application/json',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException('WaSender status check failed: ' . $curlError);
            }

            if ($httpCode >= 400) {
                $decodedError = json_decode($responseBody, true);
                throw new \RuntimeException(
                    $decodedError['message'] ?? 'WaSender status check failed.'
                );
            }

            $apiResponse = json_decode($responseBody, true);
            $status = strtolower((string) ($apiResponse['status'] ?? 'inactive'));
            $session->update([
                'status' => $status,
            ]);

            return response()->json([
                'channel' => 'whatsapp',
                'schema_name' => $schemaName,
                'status' => $status,
                'balance' => null,
                'last_active' => null,
                'api_response' => $apiResponse,
                'error' => null,
                'message' => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'channel' => 'whatsapp',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'channel' => 'whatsapp',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 500);
        }
    }

    /**
     * Check SMS (Beem) health for a given schema
     */
    public function checkSms(Request $request)
    {
        try {
            $request->validate([
                'schema_name' => 'required|string',
            ]);

            $schemaName = $request->input('schema_name');
            $apiKey = config('notification.providers.beem.api_key');
            $secretKey = config('notification.providers.beem.secret_key');

            if (empty($apiKey) || empty($secretKey)) {
                return response()->json([
                    'channel' => 'sms',
                    'schema_name' => $schemaName,
                    'status' => 'inactive',
                    'message' => 'Beem API credentials are not configured.',
                ], 503);
            }

            $ch = curl_init('https://apisms.beem.africa/public/v1/vendors/balance');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($apiKey . ':' . $secretKey),
                    'Content-Type: application/json',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException('Beem balance check failed: ' . $curlError);
            }

            if ($httpCode >= 400) {
                $decodedError = json_decode($responseBody, true);
                throw new \RuntimeException(
                    $decodedError['message'] ?? 'Beem balance check failed.'
                );
            }

            $apiResponse = json_decode($responseBody, true);
            $creditBalance = $apiResponse['data']['credit_balance'] ?? null;
            $hasBalance = !empty($apiResponse) && !is_null($creditBalance) && $creditBalance !== false;
            if ($schemaName != 'shulesoft') {
                $notificationController = app(NotificationController::class);
                $creditBalance = $notificationController->processBalance($schemaName)['balance'] ?? 0;
            }

            return response()->json([
                'channel' => 'sms',
                'schema_name' => $schemaName,
                'status' => $hasBalance ? 'active' : 'inactive',
                'balance' => $creditBalance,
                'last_active' => null,
                'api_response' => $apiResponse,
                'error' => null,
                'message' => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'channel' => 'sms',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'channel' => 'sms',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 500);
        }
    }

    /**
     * Check Phone-SMS health for a given schema using admin.school_keys last_active
     */
    public function checkPhoneSms(Request $request)
    {
        try {
            $request->validate([
                'schema_name' => 'required|string',
            ]);

            $schemaName = $request->input('schema_name');
            $record = DB::connection('shulesoft')
                ->table('admin.school_keys')
                ->where('schema_name', $schemaName)
                ->first();

            $status = 'inactive';
            if ($record && !is_null($record->last_active)) {
                $lastActive = $record->last_active instanceof \DateTimeInterface
                    ? $record->last_active
                    : new \DateTimeImmutable((string) $record->last_active);

                $threshold = now()->subHours(24);
                $status = $lastActive >= $threshold ? 'active' : 'inactive';
            }

            return response()->json([
                'channel' => 'phone-sms',
                'schema_name' => $schemaName,
                'status' => $status,
                'balance' => null,
                'last_active' => $record && !is_null($record->last_active) ? $record->last_active : null,
                'api_response' => null,
                'error' => null,
                'message' => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'channel' => 'phone-sms',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'channel' => 'phone-sms',
                'schema_name' => $request->input('schema_name'),
                'status' => 'inactive',
                'balance' => null,
                'last_active' => null,
                'api_response' => null,
                'error' => $e->getMessage(),
                'message' => null,
            ], 500);
        }
    }
}
