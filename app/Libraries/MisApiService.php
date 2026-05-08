<?php

namespace App\Libraries;

use Config\Services;

class MisApiService
{
    private string $baseUrl;
    private string $refreshUrl;
    private string $cacheKey;
    private string $refreshCacheKey;
    private string $fallbackAccessTokenEnv;
    private string $fallbackRefreshTokenEnv;
    private string $fallbackBaseUrlEnv;
    private string $fallbackRefreshUrlEnv;
    private int $timeout = 10;

    public function __construct()
    {
        // Match the working FUSOMS pattern first.
        $this->fallbackBaseUrlEnv = 'FOUNDATION_API_BASE_URL';
        $this->fallbackRefreshUrlEnv = 'FOUNDATION_REFRESH_URL';
        $this->fallbackAccessTokenEnv = 'FOUNDATION_ACCESS_TOKEN';
        $this->fallbackRefreshTokenEnv = 'FOUNDATION_REFRESH_TOKEN';

        $this->baseUrl = rtrim(
            (string) (env($this->fallbackBaseUrlEnv) ?: 'https://mis.foundationu.com/api/tappark'),
            '/'
        );
        $this->refreshUrl = (string) (env($this->fallbackRefreshUrlEnv) ?: 'https://mis.foundationu.com/api/token/refresh');

        $this->cacheKey = 'foundation_access_token';
        $this->refreshCacheKey = 'foundation_refresh_token';
    }

    public function getStudent(string $studentId): array
    {
        return $this->get('/student/' . rawurlencode(trim($studentId)));
    }

    public function searchStudents(string $search): array
    {
        return $this->get('/student-search/', ['search' => trim($search)]);
    }

    public function studentLogin(string $studentId, string $password): array
    {
        return $this->request('POST', '/student-login', [
            'student_id' => trim($studentId),
            'password' => $password,
        ]);
    }

    public function getEmployee(string $employeeId): array
    {
        return $this->get('/employee/' . rawurlencode(trim($employeeId)));
    }

    public function searchEmployees(string $search): array
    {
        return $this->get('/employee-search/', ['search' => trim($search)]);
    }

    public function employeeLogin(string $employeeId, string $password): array
    {
        return $this->request('POST', '/employee-login', [
            'employee_id' => trim($employeeId),
            'password' => $password,
        ]);
    }

    public function getDepartments(): array
    {
        return $this->get('/departments');
    }

    public function searchDepartments(string $search): array
    {
        return $this->get('/department-search/', ['search' => trim($search)]);
    }

    public function getDepartment(string $department): array
    {
        return $this->get('/department/' . rawurlencode(trim($department)));
    }

    public function lookupIdentity(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Identifier is required.',
                'http_status' => 400,
            ];
        }

        $studentResponse = $this->getStudent($identifier);
        if (!empty($studentResponse['success'])) {
            $studentRecord = $this->firstMatchingRecord($studentResponse, $identifier, ['data', 'student', 'students', 'result', 'results']);
            if (!empty($studentRecord)) {
                return [
                    'success' => true,
                    'data' => $studentRecord,
                    'message' => '',
                    'http_status' => (int) ($studentResponse['http_status'] ?? 200),
                    'identity_type' => 'student',
                    'match_source' => 'direct',
                ];
            }
        }

        $studentStatus = (int) ($studentResponse['http_status'] ?? 0);
        if (!in_array($studentStatus, [401, 404], true)) {
            return $studentResponse + ['identity_type' => 'student'];
        }

        $studentSearchResponse = $this->searchStudents($identifier);
        if (!empty($studentSearchResponse['success'])) {
            $studentRecord = $this->firstMatchingRecord($studentSearchResponse, $identifier, ['data', 'students', 'student', 'result', 'results']);
            if (!empty($studentRecord)) {
                return [
                    'success' => true,
                    'data' => $studentRecord,
                    'message' => '',
                    'http_status' => (int) ($studentSearchResponse['http_status'] ?? 200),
                    'identity_type' => 'student',
                    'match_source' => 'search',
                ];
            }
        }

        $employeeResponse = $this->getEmployee($identifier);
        if (!empty($employeeResponse['success'])) {
            $employeeRecord = $this->firstMatchingRecord($employeeResponse, $identifier, ['data', 'employee', 'employees', 'result', 'results']);
            if (!empty($employeeRecord)) {
                return [
                    'success' => true,
                    'data' => $employeeRecord,
                    'message' => '',
                    'http_status' => (int) ($employeeResponse['http_status'] ?? 200),
                    'identity_type' => 'employee',
                    'match_source' => 'direct',
                ];
            }
        }

        $employeeSearchResponse = $this->searchEmployees($identifier);
        if (!empty($employeeSearchResponse['success'])) {
            $employeeRecord = $this->firstMatchingRecord($employeeSearchResponse, $identifier, ['data', 'employees', 'employee', 'result', 'results']);
            if (!empty($employeeRecord)) {
                return [
                    'success' => true,
                    'data' => $employeeRecord,
                    'message' => '',
                    'http_status' => (int) ($employeeSearchResponse['http_status'] ?? 200),
                    'identity_type' => 'employee',
                    'match_source' => 'search',
                ];
            }
        }

        return $employeeResponse + ['identity_type' => 'employee'];
    }

    private function firstMatchingRecord(array $payload, string $identifier, array $preferredKeys = ['data']): array
    {
        $candidates = [];

        foreach ($preferredKeys as $key) {
            if (isset($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        foreach (['data', 'student', 'students', 'employee', 'employees', 'result', 'results'] as $key) {
            if (isset($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $unwrapped = $this->unwrapRecordEnvelope($candidate);
            if (!empty($unwrapped)) {
                return $unwrapped;
            }

            if ($this->isAssoc($candidate)) {
                return $candidate;
            }

            foreach ($candidate as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $candidateId = (string) ($item['student_id'] ?? $item['studentId'] ?? $item['employee_id'] ?? $item['employeeId'] ?? $item['id'] ?? $item['external_user_id'] ?? '');
                if ($candidateId !== '' && $candidateId === $identifier) {
                    return $item;
                }
            }

            if (!empty($candidate) && isset($candidate[0]) && is_array($candidate[0])) {
                return $candidate[0];
            }
        }

        return [];
    }

    private function unwrapRecordEnvelope(array $candidate): array
    {
        foreach (['student', 'students', 'employee', 'employees', 'department', 'departments', 'data', 'result', 'results'] as $key) {
            if (!isset($candidate[$key]) || !is_array($candidate[$key]) || empty($candidate[$key])) {
                continue;
            }

            $value = $candidate[$key];

            if ($this->isAssoc($value)) {
                return $value;
            }

            if (isset($value[0]) && is_array($value[0])) {
                return $value[0];
            }
        }

        return [];
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, [], $query);
    }

    private function request(string $method, string $endpoint, array $body = [], array $query = [], bool $allowRefreshRetry = true): array
    {
        $token = $this->getAccessToken();

        if (empty($token)) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Unable to obtain API access token.',
                'http_status' => 503,
            ];
        }

        $client = Services::curlrequest();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'http_errors' => false,
            'verify'  => true,
            'timeout' => $this->timeout,
        ];

        if (! empty($query)) {
            $options['query'] = $query;
        }

        if (! empty($body)) {
            $options['json'] = $body;
        }

        try {
            $response = $client->request($method, rtrim($this->baseUrl, '/') . $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'data' => $data,
                    'message' => '',
                    'http_status' => $statusCode,
                ];
            }

            if ($statusCode === 401) {
                if ($allowRefreshRetry) {
                    $refreshed = $this->refreshAccessToken();
                    if (!empty($refreshed)) {
                        return $this->request($method, $endpoint, $body, $query, false);
                    }
                }
            }

            $message = is_array($data) && isset($data['message'])
                ? (string) $data['message']
                : 'External API error.';

            log_message('error', "[Foundation] {$method} {$endpoint} -> {$statusCode}: {$message}");

            return [
                'success' => false,
                'data' => null,
                'message' => $message,
                'http_status' => $statusCode,
            ];
        } catch (\Throwable $e) {
            log_message('error', '[Foundation] Request exception: ' . $e->getMessage());

            return [
                'success' => false,
                'data' => null,
                'message' => 'Could not reach the Foundation API.',
                'http_status' => 502,
            ];
        }
    }

    protected function getAccessToken(): string
    {
        $cache = Services::cache();
        $token = $cache->get($this->cacheKey);

        if (!empty($token)) {
            return (string) $token;
        }

        $refreshToken = $cache->get($this->refreshCacheKey) ?: env($this->fallbackRefreshTokenEnv);
        if (empty($refreshToken)) {
            return (string) (env($this->fallbackAccessTokenEnv) ?: '');
        }

        return $this->refreshAccessToken();
    }

    protected function refreshAccessToken(): string
    {
        $cache = Services::cache();
        $refreshToken = $cache->get($this->refreshCacheKey);

        if (empty($refreshToken)) {
            $refreshToken = env($this->fallbackRefreshTokenEnv);
        }

        if (empty($refreshToken)) {
            log_message('error', '[Foundation] No refresh token available.');
            return '';
        }

        $client = Services::curlrequest();

        try {
            $response = $client->post($this->refreshUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $refreshToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
                'verify'  => true,
                'timeout' => $this->timeout,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Foundation] Token refresh exception: ' . $e->getMessage());
            return '';
        }

        if ($response->getStatusCode() !== 200) {
            log_message('error', '[Foundation] Token refresh failed: ' . $response->getBody());
            return '';
        }

        $body = json_decode($response->getBody(), true);
        if (!is_array($body)) {
            log_message('error', '[Foundation] Token refresh response was not valid JSON.');
            return '';
        }

        $newAccessToken = (string) ($body['access_token'] ?? $body['data']['access_token'] ?? '');
        $newRefreshToken = (string) ($body['refresh_token'] ?? $body['data']['refresh_token'] ?? '');

        if (empty($newAccessToken)) {
            log_message('error', '[Foundation] Refresh response missing access_token.');
            return '';
        }

        $cache->save($this->cacheKey, $newAccessToken, 110 * 60);

        if (!empty($newRefreshToken)) {
            $cache->save($this->refreshCacheKey, $newRefreshToken, 0);
        }

        return $newAccessToken;
    }
}
