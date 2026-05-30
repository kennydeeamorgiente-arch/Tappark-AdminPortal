<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Login extends BaseController
{
    protected $userModel;
    protected $session;

    public function __construct()
    {
        // Initialize UserModel and Session
        $this->userModel = new UserModel();
        $this->session = \Config\Services::session();
        helper('form');
    }

    /**
     * Display login page
     * 
     * This method returns the login view.
     * If user is already logged in, redirects to dashboard.
     * 
     * @return mixed - HTML view or redirect
     */
    public function index()
    {
        // Check if user is already logged in
        if ($this->session->get('user_id')) {
            return redirect()->to('/dashboard');
        }

        // Return the login view
        return view('login');
    }

    /**
     * Get the development admin credentials.
     *
     * These can be overridden from .env, but always stay development-only.
     */
    private function getDevelopmentAdminCredentials(): array
    {
        $email = (string) (env('TAPPARK_DEV_ADMIN_EMAIL') ?: env('tappark.devAdminEmail') ?: 'dev.admin@tappark.local');
        $password = (string) (env('TAPPARK_DEV_ADMIN_PASSWORD') ?: env('tappark.devAdminPassword') ?: 'DevAdmin123!');
        $firstName = (string) (env('TAPPARK_DEV_ADMIN_FIRST_NAME') ?: env('tappark.devAdminFirstName') ?: 'Dev');
        $lastName = (string) (env('TAPPARK_DEV_ADMIN_LAST_NAME') ?: env('tappark.devAdminLastName') ?: 'Administrator');
        $externalId = (string) (env('TAPPARK_DEV_ADMIN_EXTERNAL_ID') ?: env('tappark.devAdminExternalId') ?: 'DEV-ADMIN');

        return [
            'email' => strtolower(trim($email)),
            'password' => trim($password),
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'external_user_id' => trim($externalId),
        ];
    }

    /**
     * Allow the dev admin on local development hosts.
     */
    private function isDevelopmentAdminAllowed(): bool
    {
        if (ENVIRONMENT !== 'production') {
            return true;
        }

        $host = strtolower(trim((string) $this->request->getServer('HTTP_HOST')));
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_contains($host, 'localhost');
    }

    /**
     * Create or refresh the local development admin account and return it.
     */
    private function getDevelopmentAdminUser(string $identifier, string $password): ?array
    {
        if (!$this->isDevelopmentAdminAllowed()) {
            return null;
        }

        $credentials = $this->getDevelopmentAdminCredentials();
        $identifier = strtolower(trim($identifier));
        if ($identifier !== $credentials['email'] && $identifier !== strtolower(trim($credentials['external_user_id']))) {
            return null;
        }

        if (!hash_equals($credentials['password'], (string) $password)) {
            return null;
        }

        $payload = [
            'external_user_id' => $credentials['external_user_id'],
            'first_name' => $credentials['first_name'],
            'last_name' => $credentials['last_name'],
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'user_type_id' => UserModel::ROLE_ADMIN,
            'status' => 'active',
            'tokens' => 0,
            'assigned_area_id' => null,
        ];

        try {
            $existing = $this->userModel->where('email', $credentials['email'])->first();
            if ($existing) {
                $this->userModel->skipValidation(true);
                $this->userModel->updateUser($existing['user_id'], $payload);

                $refreshed = $this->userModel->find($existing['user_id']);
                if ($refreshed) {
                    return $refreshed;
                }
            } else {
                $this->userModel->skipValidation(true);
                $userId = $this->userModel->createUser($payload);

                if ($userId) {
                    $created = $this->userModel->find($userId);
                    if ($created) {
                        return $created;
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Development admin seed failed, falling back to synthetic session: ' . $e->getMessage());
        }

        return [
            'user_id' => 900000001,
            'external_user_id' => $credentials['external_user_id'],
            'first_name' => $credentials['first_name'],
            'last_name' => $credentials['last_name'],
            'email' => $credentials['email'],
            'user_type_id' => UserModel::ROLE_ADMIN,
            'status' => 'active',
            'tokens' => 0,
            'assigned_area_id' => null,
            'access_level' => UserModel::ROLE_ADMIN,
            'profile_picture' => null,
        ];
    }

    /**
     * Finalize a successful admin login.
     */
    private function finalizeAdminLogin(array $user, ?string $rateLimitKey = null, bool $isDevelopmentAccount = false): ResponseInterface
    {
        if (!empty($rateLimitKey)) {
            cache()->delete($rateLimitKey);
        }

        $accessLevel = (int) ($user['access_level'] ?? $user['user_type_id'] ?? UserModel::ROLE_ADMIN);
        $sessionData = [
            'user_id' => $user['user_id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'user_type_id' => $user['user_type_id'],
            'access_level' => $accessLevel,
            'is_admin' => true,
            'login_time' => time(),
            'last_activity' => time()
        ];

        if (!empty($user['profile_picture'])) {
            $sessionData['profile_picture'] = $user['profile_picture'];
        }

        $this->session->set($sessionData);

        if (method_exists($this->userModel, 'updateOnlineStatus')) {
            $this->userModel->updateOnlineStatus($user['user_id'], true);
        }

        $userName = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
        log_login($user['user_id'], trim($userName));

        $firstName = $user['first_name'] ?? 'Admin';
        $this->session->setFlashdata('welcome_message', "Welcome back, {$firstName}!");

        return $this->response->setJSON([
            'success' => true,
            'message' => $isDevelopmentAccount ? 'Development admin login successful' : 'Login successful',
            'redirect' => base_url()
        ]);
    }

    private function getUsersTableFields(): array
    {
        try {
            return $this->userModel->db->getFieldNames('users');
        } catch (\Throwable $e) {
            log_message('error', 'Unable to inspect users table fields during login: ' . $e->getMessage());
            return [];
        }
    }

    private function findLoginUser(string $identifier, array $userFields): ?array
    {
        if (empty($userFields)) {
            return null;
        }

        $identifierColumns = array_values(array_intersect(['email', 'external_user_id'], $userFields));
        if (empty($identifierColumns)) {
            log_message('error', 'Login failed: users table is missing email/external_user_id columns.');
            return null;
        }

        try {
            $query = $this->userModel->groupStart();
            foreach ($identifierColumns as $index => $column) {
                $index === 0
                    ? $query->where($column, $identifier)
                    : $query->orWhere($column, $identifier);
            }

            return $query->groupEnd()->first();
        } catch (\Throwable $e) {
            log_message('error', 'Login user lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process login form submission
     * 
     * Handles:
     * 1. Form validation (email format, required fields)
     * 2. User authentication (verify email and password)
     * 3. Check if user is admin
     * 4. Create session with user data
     * 5. Return JSON response for AJAX
     * 
     * @return mixed - JSON response
     */
    public function process()
    {
        // Only allow POST requests
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ])->setStatusCode(400);
        }

        // Get validated data
        $identifier = trim((string) ($this->request->getPost('identifier') ?? $this->request->getPost('email') ?? ''));
        $password = $this->request->getPost('password');

        if ($this->isDevelopmentAdminAllowed()) {
            $developmentAdmin = $this->getDevelopmentAdminUser($identifier, $password);
            if ($developmentAdmin) {
                return $this->finalizeAdminLogin($developmentAdmin, null, true);
            }
        }

        // Rate limiting check
        $rateLimitKey = 'login_attempt_' . md5($identifier);
        $lockoutKey = 'login_lockout_' . md5($identifier);
        $attemptCount = cache()->get($rateLimitKey) ?? 0;
        $lockoutUntil = cache()->get($lockoutKey);

        // Check if currently locked out
        if ($lockoutUntil && time() < $lockoutUntil) {
            $remaining = (int) ceil(($lockoutUntil - time()) / 60);
            return $this->response->setJSON([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$remaining} minute(s)."
            ])->setStatusCode(429);
        }

        // If lockout has expired, clear both keys and reset count
        if ($lockoutUntil && time() >= $lockoutUntil) {
            cache()->delete($rateLimitKey);
            cache()->delete($lockoutKey);
            $attemptCount = 0;
        }

        // Get validation service
        $validation = \Config\Services::validation();
        
        // Set validation rules
        $validation->setRules([
            'identifier' => 'required',
            'password' => 'required|min_length[6]'
        ], [
            'identifier' => [
                'required' => 'ID is required'
            ],
            'password' => [
                'required' => 'Password is required',
                'min_length' => 'Password must be at least 6 characters long'
            ]
        ]);

        // Run validation
        if (!$validation->withRequest($this->request)->run()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation->getErrors()
            ])->setStatusCode(422);
        }

        $userFields = $this->getUsersTableFields();
        if (empty($userFields)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Login is temporarily unavailable. Please check the database configuration.'
            ])->setStatusCode(503);
        }

        // First, check if user exists (regardless of status)
        $user = $this->findLoginUser($identifier, $userFields);

        $isMisVerifiedSubscriber = function () use ($identifier, $password): bool {
            try {
                $misResult = service('misApiService')->studentLogin($identifier, $password);
                return (bool) ($misResult['success'] ?? false);
            } catch (\Throwable $e) {
                log_message('warning', 'MIS subscriber login fallback failed: ' . $e->getMessage());
                return false;
            }
        };

        if (!$user) {
            if ($isMisVerifiedSubscriber()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.'
                ])->setStatusCode(403);
            }

            // User doesn't exist - invalid email
            // Increment rate limit counter
            $newCount = $attemptCount + 1;
            cache()->save($rateLimitKey, $newCount, 1800);
            if ($newCount >= 5) {
                cache()->save($lockoutKey, time() + 900, 900);
            }
            
            try {
                log_failed_login($identifier);
            } catch (\Exception $e) {
                log_message('error', 'Failed to log failed login attempt: ' . $e->getMessage());
            }
            
            return $this->response
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ])
                ->setStatusCode(401);
        }

        // User exists - check if status is active
        if (in_array('status', $userFields, true) && ($user['status'] ?? '') !== 'active') {
            if ($isMisVerifiedSubscriber()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.'
                ])->setStatusCode(403);
            }

            // Increment rate limit counter
            $newCount = $attemptCount + 1;
            cache()->save($rateLimitKey, $newCount, 1800);
            if ($newCount >= 5) {
                cache()->save($lockoutKey, time() + 900, 900);
            }
            
            try {
                log_failed_login($identifier);
            } catch (\Exception $e) {
                log_message('error', 'Failed to log failed login attempt: ' . $e->getMessage());
            }
            
            return $this->response
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ])
                ->setStatusCode(401);
        }

        // User exists and is active - verify password
        // Support both bcrypt-hashed and plain-text passwords
        $passwordValid = false;
        if (str_starts_with($user['password'], '$2y$') || str_starts_with($user['password'], '$2a$')) {
            // Password is bcrypt-hashed — use password_verify
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Plain-text fallback — auto-hash on successful match
            $passwordValid = ($password === $user['password']);
            if ($passwordValid) {
                // Upgrade to bcrypt hash for future logins
                $this->userModel->skipValidation(true);
                $this->userModel->update($user['user_id'], [
                    'password' => password_hash($password, PASSWORD_DEFAULT)
                ]);
                log_message('info', 'Auto-hashed plain-text password for user ID: ' . $user['user_id']);
            }
        }

        if (!$passwordValid) {
            if ($isMisVerifiedSubscriber()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.'
                ])->setStatusCode(403);
            }

            // Password is wrong - increment rate limit counter
            $newCount = $attemptCount + 1;
            cache()->save($rateLimitKey, $newCount, 1800);
            if ($newCount >= 5) {
                cache()->save($lockoutKey, time() + 900, 900);
            }
            
            try {
                log_failed_login($identifier);
            } catch (\Exception $e) {
                log_message('error', 'Failed to log failed login attempt: ' . $e->getMessage());
            }
            
            return $this->response
                ->setContentType('application/json')
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ])
                ->setStatusCode(401);
        }

        // User exists, is active, and password is correct
        // Admin access is derived from the administrator role (access level 3 / user_type_id 3)
        $accessLevel = (int) ($user['access_level'] ?? $user['user_type_id'] ?? 0);
        if ($accessLevel !== UserModel::ROLE_ADMIN) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ])->setStatusCode(403);
        }

        return $this->finalizeAdminLogin($user, $rateLimitKey, false);
    }

    /**
     * Temporary debug helper to inspect subscriber login behavior.
     */
    public function debugSubscriberLogin(): ResponseInterface
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid request method'
            ])->setStatusCode(400);
        }

        if (!$this->isDevelopmentAdminAllowed()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Debug login is only available on local development hosts.'
            ])->setStatusCode(403);
        }

        $identifier = trim((string) ($this->request->getPost('identifier') ?? $this->request->getPost('email') ?? $this->request->getPost('student_id') ?? ''));
        $password = (string) ($this->request->getPost('password') ?? '');

        if ($identifier === '' || $password === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID and password are required.'
            ])->setStatusCode(422);
        }

        $localUser = $this->userModel->db->table('users')
            ->where('external_user_id', $identifier)
            ->orWhere('email', $identifier)
            ->get()
            ->getRowArray();

        $localPasswordMatches = false;
        $localPasswordMode = 'missing';
        if ($localUser) {
            $storedPassword = (string) ($localUser['password'] ?? '');
            if ($storedPassword !== '') {
                if (str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$2a$')) {
                    $localPasswordMode = 'bcrypt';
                    $localPasswordMatches = password_verify($password, $storedPassword);
                } else {
                    $localPasswordMode = 'plain';
                    $localPasswordMatches = hash_equals($storedPassword, $password);
                }
            }
        }

        $misService = service('misApiService');
        $misResult = $misService->studentLogin($identifier, $password);

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'input' => [
                    'identifier' => $identifier,
                    'password_length' => strlen($password),
                ],
                'local' => [
                    'found' => (bool) $localUser,
                    'user_id' => $localUser['user_id'] ?? null,
                    'external_user_id' => $localUser['external_user_id'] ?? null,
                    'email' => $localUser['email'] ?? null,
                    'status' => $localUser['status'] ?? null,
                    'user_type_id' => $localUser['user_type_id'] ?? null,
                    'access_level' => $localUser['access_level'] ?? ($localUser['user_type_id'] ?? null),
                    'password_mode' => $localPasswordMode,
                    'password_matches' => $localPasswordMatches,
                ],
                'mis' => [
                    'success' => (bool) ($misResult['success'] ?? false),
                    'http_status' => $misResult['http_status'] ?? null,
                    'message' => $misResult['message'] ?? null,
                    'data_present' => !empty($misResult['data']),
                ],
            ]
        ]);
    }

    /**
     * Logout user
     * 
     * Destroys session and redirects to login page
     * 
     * @return mixed - Redirect to login page
     */
    public function logout()
    {
        $userId = $this->session->get('user_id');
        $userName = ($this->session->get('first_name') ?? '') . ' ' . ($this->session->get('last_name') ?? '');

        // Update user online status before logout (if method exists)
        if ($userId && method_exists($this->userModel, 'updateOnlineStatus')) {
            $this->userModel->updateOnlineStatus($userId, false);
        }

        // Log logout before destroying session
        if ($userId) {
            log_logout($userId, trim($userName));
        }

        // Destroy session
        $this->session->destroy();

        // Redirect to login page
        return redirect()->to('/login');
    }
}

