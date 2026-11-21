<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsService
{
    protected $cachePrefix = 'analytics:';
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Get notification statistics
     */
    public function getNotificationStats($startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $cacheKey = $this->cachePrefix . 'stats:' . $startDate->format('Y-m-d') . ':' . $endDate->format('Y-m-d');

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($startDate, $endDate) {
            return [
                'total_sent' => $this->getTotalSent($startDate, $endDate),
                'total_delivered' => $this->getTotalDelivered($startDate, $endDate),
                'total_failed' => $this->getTotalFailed($startDate, $endDate),
                'delivery_rate' => $this->getDeliveryRate($startDate, $endDate),
                'provider_breakdown' => $this->getProviderBreakdown($startDate, $endDate),
                'channel_breakdown' => $this->getChannelBreakdown($startDate, $endDate),
                'hourly_stats' => $this->getHourlyStats($startDate, $endDate),
                'daily_stats' => $this->getDailyStats($startDate, $endDate),
            ];
        });
    }

    /**
     * Get total sent notifications
     */
    protected function getTotalSent($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get total delivered notifications
     */
    protected function getTotalDelivered($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered')
            ->count();
    }

    /**
     * Get total failed notifications
     */
    protected function getTotalFailed($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'failed')
            ->count();
    }

    /**
     * Calculate delivery rate
     */
    protected function getDeliveryRate($startDate, $endDate)
    {
        $total = $this->getTotalSent($startDate, $endDate);
        $delivered = $this->getTotalDelivered($startDate, $endDate);

        return $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
    }

    /**
     * Get provider breakdown
     */
    protected function getProviderBreakdown($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->select('provider', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('provider')
            ->get()
            ->pluck('count', 'provider')
            ->toArray();
    }

    /**
     * Get channel breakdown
     */
    protected function getChannelBreakdown($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->select('channel', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('channel')
            ->get()
            ->pluck('count', 'channel')
            ->toArray();
    }

    /**
     * Get hourly statistics
     */
    protected function getHourlyStats($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('count(*) as count'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    /**
     * Get daily statistics
     */
    protected function getDailyStats($startDate, $endDate)
    {
        return DB::table('notification_logs')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as delivered'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Track notification event
     */
    public function trackEvent($event, $data = [])
    {
        try {
            DB::table('notification_events')->insert([
                'event' => $event,
                'data' => json_encode($data),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to track analytics event', [
                'event' => $event,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear analytics cache
     */
    public function clearCache()
    {
        $keys = Cache::get($this->cachePrefix . 'keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget($this->cachePrefix . 'keys');
    }
}
