<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UserController extends Controller {

    public function check(): JsonResponse {
        $firstChunk = Cache::get('users_chunk_0', []);
        $totalChunks = Cache::get('users_total_chunks', 0);
        $status = Cache::get('users_status', 'unknown');

        $totalUsers = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = Cache::get("users_chunk_{$i}", []);
            $totalUsers += count($chunk);
        }

        return response()->json([
            'status' => $status,
            'total_users' => number_format(
                $totalUsers,
                0,
                ',',
                '.'
            ),
            'sample' => array_slice($firstChunk, 0, 5),
            'timestamp' => now(),
        ]);
    }
    public function superusers(): JsonResponse {
        $start = microtime(true);

        $totalChunks = Cache::get('users_total_chunks', 0);
        $superusers = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = Cache::get("users_chunk_{$i}", []);
            foreach ($chunk as $user) {
                if (($user['score'] ?? 0) >= 900 && ($user['ativo'] ?? false) === true) {
                    $superusers[] = $user;
                }
            }
        }

        return response()->json([
            'timestamp' => now()->toISOString(),
            'execution_time_ms' => round((microtime(true) - $start) * 1000),
            'total_superusers' => count($superusers),
            'data' => $superusers,
        ]);
    }

    public function topCountries(): JsonResponse {
        $start = microtime(true);

        $totalChunks = Cache::get('users_total_chunks', 0);
        $countryTotals = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = Cache::get("users_chunk_{$i}", []);
            foreach ($chunk as $user) {
                if (($user['score'] ?? 0) >= 900 && ($user['ativo'] ?? false) === true) {
                    $country = $user['pais'] ?? 'Unknown';
                    $countryTotals[$country] = ($countryTotals[$country] ?? 0) + 1;
                }
            }
        }

        arsort($countryTotals);
        $topCountries = array_slice(
            $countryTotals,
            0,
            5,
            true
        );

        $data = [];
        foreach ($topCountries as $country => $total) {
            $data[] = ['country' => $country, 'total' => $total];
        }

        return response()->json([
            'timestamp' => now()->toISOString(),
            'execution_time_ms' => round((microtime(true) - $start) * 1000),
            'data' => $data,
        ]);
    }

    public function teamInsights(): JsonResponse {
        $start = microtime(true);

        $totalChunks = Cache::get('users_total_chunks', 0);
        $teams = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = Cache::get("users_chunk_{$i}", []);
            foreach ($chunk as $user) {
                $teamName = $user['equipe']['nome'] ?? null;
                if (!$teamName) continue;

                $teams[$teamName]['team_name'] = $teamName;
                $teams[$teamName]['total_members'] = ($teams[$teamName]['total_members'] ?? 0) + 1;
                $teams[$teamName]['total_leaders'] = ($teams[$teamName]['total_leaders'] ?? 0)
                    + ((bool) ($user['equipe']['lider'] ?? false) ? 1 : 0);
                $teams[$teamName]['total_projects'] = ($teams[$teamName]['total_projects'] ?? 0)
                    + count($user['equipe']['projetos'] ?? []);
                $teams[$teamName]['active_count'] = ($teams[$teamName]['active_count'] ?? 0)
                    + ((bool) ($user['ativo'] ?? false) ? 1 : 0);
            }
        }

        foreach ($teams as &$team) {
            $team['active_percentage'] = round((
                    $team['active_count'] / $team['total_members']) * 100,
                2
            );
            unset($team['active_count']);
        }

        return response()->json([
            'timestamp' => now()->toISOString(),
            'execution_time_ms' => round((microtime(true) - $start) * 1000),
            'data' => array_values($teams),
        ]);
    }

    public function activeUsersPerDay(Request $request): JsonResponse {
        $start = microtime(true);
        $min = (int) $request->query('min', 0);

        $totalChunks = Cache::get('users_total_chunks', 0);
        $counts = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = Cache::get("users_chunk_{$i}", []);
            foreach ($chunk as $user) {
                foreach ($user['logs'] ?? [] as $log) {
                    if (($log['acao'] ?? null) !== 'login') continue;
                    $date = $log['data'] ?? null;
                    if (!$date) continue;
                    $counts[$date] = ($counts[$date] ?? 0) + 1;
                }
            }
        }

        $data = [];
        foreach ($counts as $date => $count) {
            if ($count >= $min) {
                $data[] = ['date' => $date, 'total' => $count];
            }
        }

        usort($data, fn($a, $b)
        => strcmp($a['date'], $b['date']));

        return response()->json([
            'timestamp' => now()->toISOString(),
            'execution_time_ms' => round((microtime(
                true
            ) - $start) * 1000),
            'data' => $data,
        ]);
    }

    public function evaluation(): JsonResponse {
        $startTime = microtime(true);

        $checkResults = [];

        $hasChunksInCache = Cache::has('users_total_chunks') && Cache::get('users_total_chunks', 0) > 0;
        $hasStatusDone = Cache::get('users_status') === 'done';

        $checkResults[] = ['checkName' => 'chunksLoaded', 'passed' => $hasChunksInCache];
        $checkResults[] = ['checkName' => 'statusDone', 'passed' => $hasStatusDone];

        $apiBaseUrl = 'http://localhost:8000';

        $endpointsToTest = [
            '/api/v1/users/superusers',
            '/api/v1/users/topCountries',
            '/api/v1/users/teamInsights',
            '/api/v1/users/activeUsersPerDay',
        ];

        foreach ($endpointsToTest as $endpoint) {
            try {
                $response = Http::timeout(3)->get("{$apiBaseUrl}{$endpoint}");
                $checkResults[] = [
                    'checkName' => "GET {$endpoint}",
                    'passed' => $response->status() === 200
                ];
            } catch (\Throwable $exception) {
                $checkResults[] = [
                    'checkName' => "GET {$endpoint}",
                    'passed' => false
                ];
            }
        }

        $overallStatus = collect($checkResults)->every(fn($check) => $check['passed'])
            ? 'success'
            : (collect($checkResults)->contains(fn($check) => $check['passed']) ? 'partial' : 'failed');

        return response()->json([
            'timestamp' => now()->toISOString(),
            'executionTimeMs' => round((microtime(true) - $startTime) * 1000),
            'status' => $overallStatus,
            'checks' => $checkResults,
        ]);
    }
}
