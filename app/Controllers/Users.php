<?php

namespace App\Controllers;

use App\Models\UserModel;

class Users extends BaseController
{
    protected $userModel;
    protected $misApiService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->misApiService = service('misApiService');
        helper('activity'); // Load activity helper for logging
    }

    /**
     * Main index - returns the view
     */
    public function index()
    {
        $stats = $this->userModel->getUserStats();

        // Check if this is an AJAX request
        if ($this->request->isAJAX()) {
            // Return only the content (for AJAX loading)
            return view('pages/users/index', ['stats' => $stats]);
        }
        
        // Return full page (for direct URL access, non-AJAX)
        return view('main_layout', [
            'content' => view('pages/users/index', ['stats' => $stats])
        ]);
    }

    /**
     * Get users list (AJAX endpoint)
     */
    public function list()
    {
        // Get filters from request
        $filters = [
            'search' => $this->request->getGet('search'),
            'user_type_id' => $this->request->getGet('user_type_id'),
            'status' => $this->request->getGet('status'),
            'is_online' => $this->request->getGet('is_online')
        ];

        // Get pagination params
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('per_page') ?? 25;
        $offset = ($page - 1) * $perPage;

        // Get users
        $users = $this->userModel->getUsersWithFilters($filters, $perPage, $offset);
        $totalUsers = $this->userModel->getUsersCount($filters);
        
        // Get context-aware stats
        if (!empty($filters['user_type_id'])) {
            $stats = $this->userModel->getStatsByUserType($filters['user_type_id']);
        } else {
            $stats = $this->userModel->getUserStats();
        }

        // Calculate pagination data
        $totalPages = ceil($totalUsers / $perPage);
        $showingFrom = $totalUsers > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $showingTo = min($page * $perPage, $totalUsers);

        return $this->response->setJSON([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => $totalUsers,
                'total_pages' => $totalPages,
                'showing_from' => $showingFrom,
                'showing_to' => $showingTo
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Get single user by ID (AJAX endpoint)
     */
    public function get($userId)
    {
        $user = $this->userModel->getUserById($userId);

        if (!$user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Get user types (AJAX endpoint)
     */
    public function getUserTypes()
    {
        $userTypes = $this->userModel->getUserTypes();

        return $this->response->setJSON([
            'success' => true,
            'data' => $userTypes
        ]);
    }

    /**
     * Lookup a student from the MIS API
     */
    public function lookupStudent()
    {
        $identifier = trim((string) ($this->request->getGet('student_id') ?? $this->request->getGet('search') ?? ''));

        if ($identifier === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Subscriber ID is required.'
            ])->setStatusCode(400);
        }

        $localUser = $this->userModel->getUserByExternalId($identifier);
        if ($localUser) {
            $identityType = ((int) ($localUser['user_type_id'] ?? 0) === UserModel::ROLE_ADMIN || (int) ($localUser['user_type_id'] ?? 0) === UserModel::ROLE_ATTENDANT)
                ? 'employee'
                : 'student';

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'student_id' => $localUser['external_user_id'] ?? $identifier,
                    'external_user_id' => $localUser['external_user_id'] ?? $identifier,
                    'first_name' => $localUser['first_name'] ?? '',
                    'last_name' => $localUser['last_name'] ?? '',
                    'full_name' => trim(($localUser['first_name'] ?? '') . ' ' . ($localUser['last_name'] ?? '')),
                    'email' => $localUser['email'] ?? '',
                    'department_name' => $localUser['user_type_name'] ?? 'Subscriber',
                    'identity_type' => $identityType,
                    'source' => 'local'
                ]
            ]);
        }

        $studentResponse = $this->misApiService->lookupIdentity($identifier);

        if (empty($studentResponse['success'])) {
            $status = (int) ($studentResponse['http_status'] ?? 503);
            if ($status === 404) {
                return $this->response->setJSON([
                    'success' => false,
                    'allow_manual' => true,
                    'message' => 'No matching record was returned. Use Add Guest to create a manual account.',
                    'source' => 'mis'
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'message' => $studentResponse['message'] ?? 'Unable to locate the record.',
                'source' => 'mis'
            ])->setStatusCode($status);
        }

        $person = $this->extractMisRecord($studentResponse, $studentResponse['identity_type'] ?? 'data');
        if (empty($person)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A record was returned, but it could not be read.'
            ])->setStatusCode(502);
        }

        $formatted = $this->formatMisPerson($person, (string) ($studentResponse['identity_type'] ?? 'student'));
        $syncResult = $this->syncSubscriberFromMisRecord($formatted);

        if (!$syncResult['success']) {
            log_message('warning', 'Users::lookupStudent - local sync skipped: ' . ($syncResult['message'] ?? 'Unknown reason'));
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $formatted + ['source' => 'mis']
        ]);
    }

    /**
     * Search students in MIS
     */
    public function searchStudents()
    {
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        if ($search === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Search text is required.'
            ])->setStatusCode(400);
        }

        $studentResponse = $this->misApiService->searchStudents($search);
        if (empty($studentResponse['success'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $studentResponse['message'] ?? 'Unable to search records.'
            ])->setStatusCode((int) ($studentResponse['http_status'] ?? 502));
        }

        $students = $this->extractMisRecords($studentResponse, 'students');
        $formatted = [];
        foreach ($students as $student) {
            $formatted[] = $this->formatMisPerson($student, 'student');
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Search employees in MIS
     */
    public function searchEmployees()
    {
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        if ($search === '') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Search text is required.'
            ])->setStatusCode(400);
        }

        $employeeResponse = $this->misApiService->searchEmployees($search);
        if (empty($employeeResponse['success'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $employeeResponse['message'] ?? 'Unable to search records.'
            ])->setStatusCode((int) ($employeeResponse['http_status'] ?? 502));
        }

        $employees = $this->extractMisRecords($employeeResponse, 'employees');
        $formatted = [];
        foreach ($employees as $employee) {
            $formatted[] = $this->formatMisPerson($employee, 'employee');
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Get departments from MIS
     */
    public function getDepartments()
    {
        $departmentResponse = $this->misApiService->getDepartments();

        if (empty($departmentResponse['success'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $departmentResponse['message'] ?? 'Unable to load departments.'
            ])->setStatusCode((int) ($departmentResponse['http_status'] ?? 502));
        }

        $departments = $this->extractMisRecords($departmentResponse, 'departments');

        return $this->response->setJSON([
            'success' => true,
            'data' => array_map(function ($department) {
                return $this->formatMisDepartment($department);
            }, $departments)
        ]);
    }

    /**
     * Create new user (AJAX endpoint)
     */
    public function create()
    {
        $data = $this->request->getPost();
        $isSubscriber = (int) ($data['user_type_id'] ?? 0) === UserModel::ROLE_SUBSCRIBER;

        if ($isSubscriber) {
            $payload = [
                'external_user_id' => trim((string) ($data['external_user_id'] ?? '')),
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'user_type_id' => UserModel::ROLE_SUBSCRIBER,
                'tokens' => 0,
                'status' => 'active',
            ];

            if ($payload['external_user_id'] === '') {
                $payload['external_user_id'] = $this->buildSubscriberExternalId(
                    $payload['first_name'],
                    $payload['last_name']
                );
            }

            $errors = [];
            if ($payload['external_user_id'] === '') {
                $errors['external_user_id'] = 'Subscriber ID is required.';
            }
            if ($payload['first_name'] === '') {
                $errors['first_name'] = 'First name is required.';
            }
            if ($payload['last_name'] === '') {
                $errors['last_name'] = 'Last name is required.';
            }
            if ($payload['email'] === '') {
                $errors['email'] = 'Email is required.';
            } elseif (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address.';
            }

            if (!empty($errors)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ])->setStatusCode(400);
            }

            $existingUser = null;
            if (!empty($payload['external_user_id'])) {
                $existingUser = $this->userModel->getUserByExternalId($payload['external_user_id']);
            }

            if (!$existingUser && !empty($payload['email'])) {
                $existingUser = $this->userModel->where('email', $payload['email'])->first();
            }

            if ($existingUser) {
                unset($payload['password']);
                $result = $this->userModel->updateUser($existingUser['user_id'], $payload);
                $userId = $existingUser['user_id'];
                $message = 'Subscriber synced successfully';
                $logAction = 'User Sync';
            } else {
                $result = $this->userModel->createUser($payload);
                $userId = $result;
                $message = 'Subscriber created successfully';
                $logAction = 'User';
            }

            if ($result) {
                $user = $this->userModel->getUserById($userId);
                $stats = $this->userModel->getUserStats();
                $logResult = $existingUser
                    ? log_update($logAction, $userId, "{$payload['first_name']} {$payload['last_name']}")
                    : log_create($logAction, $userId, "{$payload['first_name']} {$payload['last_name']}");

                if ($logResult === false) {
                    log_message('error', 'Users::create - Activity log failed for user_id ' . $userId);
                }

                return $this->response->setJSON([
                    'success' => true,
                    'message' => $message,
                    'data' => $user,
                    'stats' => $stats
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to save subscriber'
            ])->setStatusCode(500);
        }

        // Keep a guarded fallback for non-subscriber records so existing behavior remains intact.
        $data['status'] = $data['status'] ?? 'active';

        if (!$this->validate([
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[8]'
        ])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        $userId = $this->userModel->createUser($data);
        if (!$userId) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to create user'
            ])->setStatusCode(500);
        }

        log_create('User', $userId, "{$data['first_name']} {$data['last_name']}");

        return $this->response->setJSON([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $this->userModel->getUserById($userId),
            'stats' => $this->userModel->getUserStats()
        ]);
    }

    /**
     * Update user (AJAX endpoint)
     */
    public function update($userId)
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

        $isSubscriber = (int) ($user['user_type_id'] ?? 0) === UserModel::ROLE_SUBSCRIBER;

        if ($isSubscriber) {
            $prepared = $this->prepareSubscriberPayload($this->request->getPost(), true, $user);
            if (!$prepared['success']) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $prepared['message'],
                    'errors' => $prepared['errors'] ?? []
                ])->setStatusCode(400);
            }

            $payload = $prepared['data'];
            $payload['status'] = $this->request->getPost('status') ?: ($user['status'] ?? 'active');
            $payload['tokens'] = $this->request->getPost('tokens') ?? $this->request->getPost('hour_balance') ?? ($user['tokens'] ?? $user['hour_balance'] ?? 0);
            $payload['user_type_id'] = UserModel::ROLE_SUBSCRIBER;

            if ($this->userModel->updateUser($userId, $payload)) {
                log_update('User', $userId, "{$payload['first_name']} {$payload['last_name']}");

                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'User updated successfully',
                    'data' => $this->userModel->getUserById($userId),
                    'stats' => $this->userModel->getUserStats()
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to update user'
            ])->setStatusCode(500);
        }

        $data = [
            'first_name' => $this->request->getPost('first_name'),
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'user_type_id' => $this->request->getPost('user_type_id'),
            'status' => $this->request->getPost('status'),
            'tokens' => $this->request->getPost('tokens') ?? $this->request->getPost('hour_balance')
        ];

        $password = $this->request->getPost('password');
        if (!empty($password)) {
            $data['password'] = $password;
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => "required|valid_email|is_unique[users.email,user_id,{$userId}]"
        ];

        if (!empty($password)) {
            $rules['password'] = 'min_length[8]';
        }

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        if ($this->userModel->updateUser($userId, $data)) {
            log_update('User', $userId, "{$data['first_name']} {$data['last_name']}");

            return $this->response->setJSON([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $this->userModel->getUserById($userId),
                'stats' => $this->userModel->getUserStats()
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to update user'
        ])->setStatusCode(500);
    }

    /**
     * Delete user (AJAX endpoint)
     */
    public function delete($userId)
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'User not found'
            ])->setStatusCode(404);
        }

        // Subscribers are now managed from MIS and should not be deleted locally.
        if ((int) ($user['user_type_id'] ?? 0) === UserModel::ROLE_SUBSCRIBER) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Subscriber deletion is disabled. Please update the subscriber token balance or status instead.'
            ])->setStatusCode(403);
        }

        if ($this->userModel->deleteUser($userId)) {
            // Log activity
            $logResult = log_delete('User', $userId, "{$user['first_name']} {$user['last_name']}");
            if ($logResult === false) {
                log_message('error', 'Users::delete - Activity log failed for user_id ' . $userId);
            }

            // Get updated stats
            $stats = $this->userModel->getUserStats();

            return $this->response->setJSON([
                'success' => true,
                'message' => 'User deleted successfully',
                'stats' => $stats
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Failed to delete user'
        ])->setStatusCode(500);
    }

    /**
     * Export users to CSV based on current filters
     */
    public function export()
    {
        // Get filters from request (same as list)
        $filters = [
            'search' => $this->request->getGet('search'),
            'user_type_id' => $this->request->getGet('user_type_id'),
            'status' => $this->request->getGet('status'),
            'is_online' => $this->request->getGet('is_online')
        ];

        // Get ALL users with filters (no pagination for export)
        $users = $this->userModel->getUsersWithFilters($filters, 999999, 0);
        
        // Generate filename with current date
        $filename = 'Users_Export_' . date('Y-m-d_His') . '.csv';
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps Excel recognize UTF-8)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add CSV headers
        fputcsv($output, [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'User Type',
            'Tokens',
            'Status',
            'Online Status',
            'Created At'
        ]);
        
        // Add data rows
        foreach ($users as $user) {
            // Format created_at safely
            $createdAt = '';
            if (!empty($user['created_at'])) {
                $timestamp = strtotime($user['created_at']);
                if ($timestamp !== false) {
                    $createdAt = date('Y-m-d H:i:s', $timestamp);
                }
            }
            
            fputcsv($output, [
                $user['user_id'] ?? $user['id'] ?? '',
                $user['first_name'] ?? '',
                $user['last_name'] ?? '',
                $user['email'] ?? '',
                $user['user_type_name'] ?? $user['type_name'] ?? 'N/A',
                $user['tokens'] ?? $user['hour_balance'] ?? 0,
                ucfirst($user['status'] ?? 'N/A'),
                (!empty($user['is_online']) && $user['is_online']) ? 'Online' : 'Offline',
                $createdAt
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Download a CSV template for bulk user import.
     */
    public function importTemplate()
    {
        $filename = 'Users_Import_Template_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'external_user_id',
            'first_name',
            'last_name',
            'email',
            'user_type_id',
            'tokens',
            'status',
            'password',
            'assigned_area_id'
        ]);

        fputcsv($output, ['20231234', 'Juan', 'Dela Cruz', 'juan.delacruz@foundationu.com', '1', '0', 'active', '', '']);
        fputcsv($output, ['A-1001', 'Maria', 'Santos', 'maria.santos@foundationu.com', '2', '0', 'active', '', '']);
        fputcsv($output, ['ADM-001', 'Ana', 'Reyes', 'ana.reyes@foundationu.com', '3', '0', 'active', '', '']);

        fclose($output);
        exit;
    }

    /**
     * Bulk import users from a CSV file.
     */
    public function import()
    {
        $file = $this->request->getFile('import_file');

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Please upload a valid CSV file.'
            ])->setStatusCode(400);
        }

        $extension = strtolower((string) $file->getClientExtension());
        if ($extension !== 'csv') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Only CSV files are supported for now. Please save the sheet as CSV and try again.'
            ])->setStatusCode(400);
        }

        $handle = fopen($file->getTempName(), 'r');
        if ($handle === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unable to read the uploaded file.'
            ])->setStatusCode(500);
        }

        $headerRow = fgetcsv($handle);
        if ($headerRow === false || empty($headerRow)) {
            fclose($handle);
            return $this->response->setJSON([
                'success' => false,
                'message' => 'The CSV file is empty.'
            ])->setStatusCode(400);
        }

        $headers = $this->normalizeImportHeaders($headerRow);
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($row === [null] || empty(array_filter($row, static fn ($value) => trim((string) $value) !== ''))) {
                continue;
            }

            $record = $this->mapImportRow($headers, $row);
            $processed = $this->processImportedUserRow($record, $rowNumber);

            if ($processed['success']) {
                $results[$processed['action']]++;
                continue;
            }

            $results['skipped']++;
            $results['errors'][] = [
                'row' => $rowNumber,
                'message' => $processed['message']
            ];
        }

        fclose($handle);

        $message = sprintf(
            'Import complete. %d created, %d updated, %d skipped.',
            $results['created'],
            $results['updated'],
            $results['skipped']
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'results' => $results,
            'stats' => $this->userModel->getUserStats()
        ]);
    }

    /**
     * ============================================================================
     * WALK-IN GUESTS MANAGEMENT METHODS
     * ============================================================================
     */

    /**
     * Get walk-in guests (guest bookings) list (AJAX endpoint)
     */
    public function getWalkInGuests()
    {
        try {
            // Get filters from request
            $filters = [
                'search' => $this->request->getGet('search'),
                'attendant_id' => $this->request->getGet('attendant_id'),
                'vehicle_type' => $this->request->getGet('vehicle_type'),
                'date_range' => $this->request->getGet('date_range')
            ];
            
            // Get pagination params
            $page = $this->request->getGet('page') ?? 1;
            $perPage = $this->request->getGet('per_page') ?? 25;
            $sortColumn = $this->request->getGet('sort_column') ?? 'guest_booking_id';
            $sortDirection = $this->request->getGet('sort_direction') ?? 'desc';
            
            // Build the query with JOINs to get real names and details
            $db = \Config\Database::connect();
        
            $builder = $db->table('guest_bookings gb')
                ->select('
                    gb.guest_booking_id,
                    gb.guest_user_id,
                    gb.vehicle_id,
                    gb.reservation_id,
                    gb.attendant_id,
                    gb.created_at,
                    gu.first_name as guest_first_name,
                    gu.last_name as guest_last_name,
                    gu.email as guest_email,
                    v.vehicle_type,
                    v.brand as vehicle_brand,
                    v.color as vehicle_color,
                    v.plate_number,
                    u.first_name as attendant_first_name,
                    u.last_name as attendant_last_name,
                    t.account_type_name as attendant_role,
                    r.booking_status as reservation_status
                ')
                ->join('users gu', 'gu.user_id = gb.guest_user_id', 'left')
                ->join('vehicles v', 'v.vehicle_id = gb.vehicle_id', 'left')
                ->join('users u', 'u.user_id = gb.attendant_id', 'left')
                ->join('types t', 't.type_id = u.user_type_id', 'left')
                ->join('reservations r', 'r.reservation_id = gb.reservation_id', 'left');
        
            // Apply filters
            if (!empty($filters['search'])) {
                $builder->groupStart()
                    ->like('gu.first_name', $filters['search'])
                    ->orLike('gu.last_name', $filters['search'])
                    ->orLike('CONCAT(gu.first_name, " ", gu.last_name)', $filters['search'])
                    ->orLike('v.plate_number', $filters['search'])
                    ->groupEnd();
            }
            
            if (!empty($filters['attendant_id'])) {
                $builder->where('gb.attendant_id', $filters['attendant_id']);
            }
            
            if (!empty($filters['vehicle_type'])) {
                $builder->where('v.vehicle_type', $filters['vehicle_type']);
            }
            
            if (!empty($filters['date_range'])) {
                $builder->where($this->getDateRangeCondition($filters['date_range'], 'gb.created_at'));
            }
            
            // Apply sorting
            $allowedSortColumns = ['guest_booking_id', 'guest_name', 'vehicle_type', 'attendant_name', 'created_at'];
            if (in_array($sortColumn, $allowedSortColumns)) {
                if ($sortColumn === 'guest_name') {
                    $builder->orderBy('gu.first_name', $sortDirection)
                             ->orderBy('gu.last_name', $sortDirection);
                } elseif ($sortColumn === 'attendant_name') {
                    $builder->orderBy('u.first_name', $sortDirection)
                             ->orderBy('u.last_name', $sortDirection);
                } else {
                    $builder->orderBy($sortColumn, $sortDirection);
                }
            } else {
                $builder->orderBy('gb.guest_booking_id', 'desc');
            }
            
            // Get total count for pagination
            $total = $builder->countAllResults(false);
            
            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $builder->limit($perPage, $offset);
            
            // Get results
            $bookings = $builder->get()->getResultArray();
            
            // Format the data
            $formattedBookings = [];
            foreach ($bookings as $booking) {
                $formattedBookings[] = [
                    'guest_booking_id' => $booking['guest_booking_id'],
                    'guest_name' => trim($booking['guest_first_name'] . ' ' . $booking['guest_last_name']),
                    'guest_email' => $booking['guest_email'],
                    'vehicle_type' => $booking['vehicle_type'],
                    'vehicle_brand' => $booking['vehicle_brand'],
                    'vehicle_color' => $booking['vehicle_color'],
                    'plate_number' => $booking['plate_number'],
                    'attendant_name' => trim($booking['attendant_first_name'] . ' ' . $booking['attendant_last_name']),
                    'attendant_role' => $booking['attendant_role'],
                    'reservation_id' => $booking['reservation_id'],
                    'reservation_status' => $booking['reservation_status'],
                    'created_at' => $booking['created_at']
                ];
            }
            
            // Get stats for walk-in guests
            $today = date('Y-m-d');
            $stats = [
                'total' => $db->table('guest_bookings')->countAllResults(),
                'today' => $db->table('guest_bookings')
                    ->where('DATE(created_at)', $today)
                    ->countAllResults(),
                'parked' => $db->table('guest_bookings gb')
                    ->join('reservations r', 'r.reservation_id = gb.reservation_id', 'left')
                    ->whereIn('r.booking_status', ['confirmed', 'active'])
                    ->countAllResults(),
                'completed' => $db->table('guest_bookings gb')
                    ->join('reservations r', 'r.reservation_id = gb.reservation_id', 'left')
                    ->whereIn('r.booking_status', ['completed', 'cancelled'])
                    ->countAllResults()
            ];

            // Return paginated response
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'data' => $formattedBookings,
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => (int)$total,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $perPage, $total)
                ],
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getWalkInGuests: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred while loading walk-in guests: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    /**
     * Get attendants list for filter dropdown
     */
    public function getAttendantsList()
    {
        try {
            $db = \Config\Database::connect();
            
            $attendants = $db->table('users u')
                ->select('u.user_id, u.first_name, u.last_name, t.account_type_name')
                ->join('types t', 't.type_id = u.user_type_id', 'left')
                ->whereIn('u.user_type_id', [UserModel::ROLE_ATTENDANT, UserModel::ROLE_ADMIN]) // Attendant and Admin
                ->orderBy('u.first_name', 'asc')
                ->get()
                ->getResultArray();
            
            $formattedAttendants = [];
            foreach ($attendants as $attendant) {
                $formattedAttendants[] = [
                    'user_id' => $attendant['user_id'],
                    'name' => trim($attendant['first_name'] . ' ' . $attendant['last_name']),
                    'role' => $attendant['account_type_name']
                ];
            }
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $formattedAttendants
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getAttendantsList: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    /**
     * Get individual walk-in guest booking details
     */
    public function getWalkInGuestDetails($bookingId)
    {
        try {
            $db = \Config\Database::connect();
            
            $booking = $db->table('guest_bookings gb')
                ->select('
                    gb.guest_booking_id,
                    gb.guest_user_id,
                    gb.vehicle_id,
                    gb.reservation_id,
                    gb.attendant_id,
                    gb.created_at,
                    gu.first_name as guest_first_name,
                    gu.last_name as guest_last_name,
                    gu.email as guest_email,
                    v.vehicle_type,
                    v.brand as vehicle_brand,
                    v.color as vehicle_color,
                    v.plate_number,
                    u.first_name as attendant_first_name,
                    u.last_name as attendant_last_name,
                    t.account_type_name as attendant_role,
                    r.booking_status as reservation_status,
                    r.start_time,
                    r.end_time,
                    r.QR as qr_code
                ')
                ->join('users gu', 'gu.user_id = gb.guest_user_id', 'left')
                ->join('vehicles v', 'v.vehicle_id = gb.vehicle_id', 'left')
                ->join('users u', 'u.user_id = gb.attendant_id', 'left')
                ->join('types t', 't.type_id = u.user_type_id', 'left')
                ->join('reservations r', 'r.reservation_id = gb.reservation_id', 'left')
                ->where('gb.guest_booking_id', $bookingId)
                ->get()
                ->getRowArray();
            
            if (!$booking) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Walk-in guest booking not found'
                ])->setStatusCode(404);
            }
            
            // Format the data
            $formattedBooking = [
                'guest_booking_id' => $booking['guest_booking_id'],
                'guest_name' => trim($booking['guest_first_name'] . ' ' . $booking['guest_last_name']),
                'guest_email' => $booking['guest_email'],
                'vehicle_type' => $booking['vehicle_type'],
                'vehicle_brand' => $booking['vehicle_brand'],
                'vehicle_color' => $booking['vehicle_color'],
                'plate_number' => $booking['plate_number'],
                'attendant_name' => trim($booking['attendant_first_name'] . ' ' . $booking['attendant_last_name']),
                'attendant_role' => $booking['attendant_role'],
                'reservation_id' => $booking['reservation_id'],
                'reservation_status' => $booking['reservation_status'],
                'start_time' => $booking['start_time'] ?? null,
                'end_time' => $booking['end_time'] ?? null,
                'qr_code' => $booking['qr_code'] ?? null,
                'created_at' => $booking['created_at']
            ];
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $formattedBooking
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getWalkInGuestDetails: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    /**
     * Get vehicle types for filter dropdown
     */
    public function getVehicleTypes()
    {
        try {
            $db = \Config\Database::connect();
            
            // Get distinct vehicle types from vehicles table
            $types = $db->table('vehicles')
                ->select('vehicle_type')
                ->distinct()
                ->where('vehicle_type IS NOT NULL')
                ->where('vehicle_type !=', '')
                ->orderBy('vehicle_type', 'ASC')
                ->get()
                ->getResultArray();
            
            // Format for dropdown
            $formattedTypes = [];
            foreach ($types as $type) {
                if (!empty($type['vehicle_type'])) {
                    $formattedTypes[] = [
                        'value' => $type['vehicle_type'],
                        'label' => ucfirst($type['vehicle_type'])
                    ];
                }
            }
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $formattedTypes
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getVehicleTypes: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    /**
     * Export walk-in guests to CSV
     */
    public function exportWalkInGuests()
    {
        // Get filters from request
        $filters = [
            'search' => $this->request->getGet('search'),
            'attendant_id' => $this->request->getGet('attendant_id'),
            'vehicle_type' => $this->request->getGet('vehicle_type'),
            'date_range' => $this->request->getGet('date_range')
        ];
        
        $db = \Config\Database::connect();
        
        $builder = $db->table('guest_bookings gb')
            ->select('
                gb.guest_booking_id,
                gu.first_name as guest_first_name,
                gu.last_name as guest_last_name,
                gu.email as guest_email,
                v.vehicle_type,
                v.brand as vehicle_brand,
                v.color as vehicle_color,
                v.plate_number,
                u.first_name as attendant_first_name,
                u.last_name as attendant_last_name,
                t.account_type_name as attendant_role,
                r.reservation_id,
                r.booking_status as reservation_status,
                gb.created_at
            ')
            ->join('users gu', 'gu.user_id = gb.guest_user_id', 'left')
            ->join('vehicles v', 'v.vehicle_id = gb.vehicle_id', 'left')
            ->join('users u', 'u.user_id = gb.attendant_id', 'left')
            ->join('types t', 't.type_id = u.user_type_id', 'left')
            ->join('reservations r', 'r.reservation_id = gb.reservation_id', 'left');
        
        // Apply same filters as getWalkInGuests
        if (!empty($filters['search'])) {
            $builder->groupStart()
                ->like('gu.first_name', $filters['search'])
                ->orLike('gu.last_name', $filters['search'])
                ->orLike('CONCAT(gu.first_name, " ", gu.last_name)', $filters['search'])
                ->orLike('v.plate_number', $filters['search'])
                ->groupEnd();
        }
        
        if (!empty($filters['attendant_id'])) {
            $builder->where('gb.attendant_id', $filters['attendant_id']);
        }
        
        if (!empty($filters['vehicle_type'])) {
            $builder->where('v.vehicle_type', $filters['vehicle_type']);
        }
        
        if (!empty($filters['date_range'])) {
            $builder->where($this->getDateRangeCondition($filters['date_range'], 'gb.created_at'));
        }
        
        $builder->orderBy('gb.guest_booking_id', 'desc');
        $bookings = $builder->get()->getResultArray();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="walk_in_guests_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, [
            'Booking ID',
            'Guest Name',
            'Guest Email',
            'Vehicle Type',
            'Vehicle Brand',
            'Vehicle Color',
            'Plate Number',
            'Attendant Name',
            'Attendant Role',
            'Reservation ID',
            'Reservation Status',
            'Created At'
        ]);
        
        // CSV data
        foreach ($bookings as $booking) {
            fputcsv($output, [
                $booking['guest_booking_id'] ?? '',
                trim($booking['guest_first_name'] . ' ' . $booking['guest_last_name']),
                $booking['guest_email'] ?? '',
                $booking['vehicle_type'] ?? '',
                $booking['vehicle_brand'] ?? '',
                $booking['vehicle_color'] ?? '',
                $booking['plate_number'] ?? '',
                trim($booking['attendant_first_name'] . ' ' . $booking['attendant_last_name']),
                $booking['attendant_role'] ?? '',
                $booking['reservation_id'] ?? '',
                $booking['reservation_status'] ?? '',
                $booking['created_at'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Helper function to get date range condition
     */
    private function getDateRangeCondition($range, $field)
    {
        switch ($range) {
            case 'today':
                return "DATE($field) = CURDATE()";
            case 'yesterday':
                return "DATE($field) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            case 'week':
                return "YEAR($field) = YEAR(CURDATE()) AND WEEK($field) = WEEK(CURDATE())";
            case 'month':
                return "YEAR($field) = YEAR(CURDATE()) AND MONTH($field) = MONTH(CURDATE())";
            case 'last30':
                return "$field >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            case 'last90':
                return "$field >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
            default:
                return "1=1";
        }
    }

    /**
     * Extract a single MIS record from a response payload.
     */
    private function extractMisRecord(array $payload, string $preferredKey = 'data'): array
    {
        $candidates = [];

        if (isset($payload[$preferredKey])) {
            $candidates[] = $payload[$preferredKey];
        }

        foreach (['data', 'student', 'employee', 'department', 'result', 'results'] as $key) {
            if (isset($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        foreach ($candidates as $candidate) {
            $record = $this->normalizeMisRecord($candidate);
            if (!empty($record)) {
                return $record;
            }
        }

        return [];
    }

    /**
     * Extract a list of MIS records from a response payload.
     */
    private function extractMisRecords(array $payload, string $preferredKey = 'data'): array
    {
        $candidates = [];

        if (isset($payload[$preferredKey])) {
            $candidates[] = $payload[$preferredKey];
        }

        foreach (['data', 'students', 'employees', 'departments', 'result', 'results'] as $key) {
            if (isset($payload[$key])) {
                $candidates[] = $payload[$key];
            }
        }

        foreach ($candidates as $candidate) {
            $records = $this->normalizeMisRecords($candidate);
            if (!empty($records)) {
                return $records;
            }
        }

        return [];
    }

    /**
     * Normalize a student record returned by MIS.
     */
    private function formatMisPerson(array $person, string $identityType = 'student'): array
    {
        $person = $this->normalizeMisRecord($person);

        $identifier = (string) ($person['student_id'] ?? $person['employee_id'] ?? $person['studentId'] ?? $person['employeeId'] ?? $person['id'] ?? $person['external_user_id'] ?? '');
        $firstName = trim((string) ($person['first_name'] ?? $person['firstname'] ?? $person['given_name'] ?? $person['firstName'] ?? ''));
        $lastName = trim((string) ($person['last_name'] ?? $person['lastname'] ?? $person['surname'] ?? $person['lastName'] ?? ''));
        $email = trim((string) ($person['email'] ?? $person['email_address'] ?? $person['school_email'] ?? $person['student_email'] ?? $person['employee_email'] ?? ''));
        $department = trim((string) ($person['department'] ?? $person['department_name'] ?? $person['program'] ?? $person['course'] ?? $person['division'] ?? ''));

        if ($email === '') {
            $email = $this->buildFoundationEmail($firstName, $lastName, $identifier);
        }

        return [
            'student_id' => $identifier,
            'external_user_id' => $identifier,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => trim($firstName . ' ' . $lastName),
            'email' => $email,
            'department_name' => $department,
            'identity_type' => $identityType,
            'raw' => $person
        ];
    }

    /**
     * Normalize a single MIS record, unwrapping common envelopes.
     */
    private function normalizeMisRecord($candidate): array
    {
        if (!is_array($candidate) || empty($candidate)) {
            return [];
        }

        foreach (['student', 'employee', 'department', 'data', 'result', 'record', 'item'] as $key) {
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

        if ($this->isAssoc($candidate)) {
            return $candidate;
        }

        if (isset($candidate[0]) && is_array($candidate[0])) {
            return $candidate[0];
        }

        return [];
    }

    /**
     * Normalize MIS list payloads into an array of records.
     */
    private function normalizeMisRecords($candidate): array
    {
        if (!is_array($candidate) || empty($candidate)) {
            return [];
        }

        foreach (['students', 'employees', 'departments', 'data', 'result', 'results'] as $key) {
            if (!isset($candidate[$key]) || !is_array($candidate[$key]) || empty($candidate[$key])) {
                continue;
            }

            $value = $candidate[$key];
            if (!$this->isAssoc($value)) {
                return $value;
            }

            return [$value];
        }

        if (!$this->isAssoc($candidate)) {
            return $candidate;
        }

        $record = $this->normalizeMisRecord($candidate);
        return !empty($record) ? [$record] : [];
    }

    /**
     * Normalize a department record returned by MIS.
     */
    private function formatMisDepartment(array $department): array
    {
        $departmentName = (string) ($department['department'] ?? $department['department_name'] ?? $department['name'] ?? $department['title'] ?? '');

        return [
            'department' => $departmentName,
            'department_name' => $departmentName,
            'raw' => $department
        ];
    }

    /**
     * Build a subscriber payload from MIS or fallback request data.
     */
    private function prepareSubscriberPayload(array $data, bool $isUpdate = false, ?array $existingUser = null): array
    {
        $externalUserId = trim((string) ($data['external_user_id'] ?? $data['student_id'] ?? ($existingUser['external_user_id'] ?? '')));

        if ($externalUserId === '' && !$isUpdate) {
            return [
                'success' => false,
                'message' => 'Subscriber ID is required.',
                'errors' => ['external_user_id' => 'Subscriber ID is required.']
            ];
        }

        $misData = [];
        if ($externalUserId !== '') {
            $lookup = $this->misApiService->getStudent($externalUserId);
            if (empty($lookup['success'])) {
                if (!$isUpdate) {
                    return [
                        'success' => false,
                        'message' => $lookup['message'] ?? 'Unable to fetch the subscriber record.',
                        'errors' => ['external_user_id' => 'Unable to fetch the subscriber record.']
                    ];
                }

                log_message('warning', 'MIS lookup failed for subscriber update: ' . ($lookup['message'] ?? 'Unknown error'));
            } else {
                $misData = $this->extractMisRecord($lookup, $lookup['identity_type'] ?? 'data');
                if (empty($misData) && !$isUpdate) {
                    return [
                        'success' => false,
                        'message' => 'A record was returned, but it could not be parsed.',
                        'errors' => ['external_user_id' => 'A record was returned, but it could not be parsed.']
                    ];
                }
            }
        }

        $mapped = !empty($misData)
            ? $this->formatMisPerson($misData, (string) ($misData['identity_type'] ?? 'student'))
            : [
                'student_id' => $externalUserId,
                'external_user_id' => $externalUserId,
                'first_name' => trim((string) ($data['first_name'] ?? $existingUser['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? $existingUser['last_name'] ?? '')),
                'email' => trim((string) ($data['email'] ?? $existingUser['email'] ?? '')),
                'department_name' => '',
                'raw' => [],
            ];

        $payload = [
            'external_user_id' => $mapped['external_user_id'],
            'first_name' => $mapped['first_name'],
            'last_name' => $mapped['last_name'],
            'email' => $mapped['email'],
            'user_type_id' => UserModel::ROLE_SUBSCRIBER,
            'tokens' => (int) ($data['tokens'] ?? $data['hour_balance'] ?? ($existingUser['tokens'] ?? $existingUser['hour_balance'] ?? 0)),
            'status' => $data['status'] ?? ($existingUser['status'] ?? 'active'),
        ];

        $errors = [];
        if ($payload['external_user_id'] === '') {
            $errors['external_user_id'] = 'Subscriber ID is required.';
        }
        if ($payload['first_name'] === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($payload['last_name'] === '') {
            $errors['last_name'] = 'Last name is required.';
        }
        if ($payload['email'] === '') {
            $errors['email'] = 'Email is required.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors
            ];
        }

        if (!$isUpdate && empty($data['password'])) {
            $payload['password'] = $this->generateTemporaryPassword($payload['external_user_id']);
        } elseif (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        return [
            'success' => true,
            'data' => $payload
        ];
    }

    /**
     * Generate a temporary password for MIS-synced subscribers.
     */
    private function generateTemporaryPassword(string $seed): string
    {
        return 'TapPark-' . substr(hash('sha256', $seed . '|' . bin2hex(random_bytes(8))), 0, 12);
    }

    /**
     * Upsert a MIS subscriber into the local database.
     */
    private function syncSubscriberFromMisRecord(array $person): array
    {
        $externalUserId = trim((string) ($person['external_user_id'] ?? $person['student_id'] ?? ''));

        if ($externalUserId === '') {
            return [
                'success' => false,
                'message' => 'Missing external user ID.'
            ];
        }

        $existingUser = $this->userModel->getUserByExternalId($externalUserId);
        $payload = [
            'external_user_id' => $externalUserId,
            'first_name' => trim((string) ($person['first_name'] ?? '')),
            'last_name' => trim((string) ($person['last_name'] ?? '')),
            'email' => trim((string) ($person['email'] ?? '')),
            'user_type_id' => UserModel::ROLE_SUBSCRIBER,
            'tokens' => $existingUser ? (int) ($existingUser['tokens'] ?? $existingUser['hour_balance'] ?? 0) : 0,
            'status' => $existingUser['status'] ?? 'active',
        ];

        if ($payload['first_name'] === '' || $payload['last_name'] === '') {
            return [
                'success' => false,
                'message' => 'Incomplete MIS record.'
            ];
        }

        if ($existingUser) {
            $result = $this->userModel->updateUser($existingUser['user_id'], $payload);
            return [
                'success' => (bool) $result,
                'message' => $result ? 'Subscriber updated locally.' : 'Failed to update local subscriber.'
            ];
        }

        if ($payload['email'] === '') {
            $payload['email'] = $this->buildFoundationEmail(
                (string) ($payload['first_name'] ?? ''),
                (string) ($payload['last_name'] ?? ''),
                $externalUserId
            );
        }

        $payload['password'] = $this->generateTemporaryPassword($externalUserId);
        $result = $this->userModel->createUser($payload);

        return [
            'success' => (bool) $result,
            'message' => $result ? 'Subscriber created locally.' : 'Failed to create local subscriber.'
        ];
    }

    /**
     * Check whether an array is associative.
     */
    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Build a Foundation University email address from a person's name.
     */
    private function buildFoundationEmail(string $firstName, string $lastName, string $fallback = ''): string
    {
        $firstName = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $firstName));
        $lastName = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $lastName));

        $localPart = $firstName;
        if ($lastName !== '') {
            $localPart .= ($localPart !== '' ? '.' : '') . $lastName;
        }

        if ($localPart === '') {
            $localPart = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $fallback));
        }

        return $localPart !== ''
            ? $localPart . '@foundationu.com'
            : '';
    }

    /**
     * Build a suggested subscriber ID from a person's name.
     */
    private function buildSubscriberExternalId(string $firstName, string $lastName, string $fallback = ''): string
    {
        $firstName = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $firstName));
        $lastName = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $lastName));

        $localPart = $firstName;
        if ($lastName !== '') {
            $localPart .= ($localPart !== '' ? '.' : '') . $lastName;
        }

        if ($localPart === '') {
            $localPart = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $fallback));
        }

        return $localPart;
    }

    /**
     * Normalize CSV headers into snake_case keys.
     */
    private function normalizeImportHeaders(array $headers): array
    {
        return array_map(static function ($header) {
            $header = (string) $header;
            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            $header = strtolower(trim($header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header);
            return trim((string) $header, '_');
        }, $headers);
    }

    /**
     * Map a CSV row to an associative array.
     */
    private function mapImportRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $mapped[$header] = trim((string) ($row[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * Process a single imported user row.
     */
    private function processImportedUserRow(array $record, int $rowNumber): array
    {
        $externalUserId = trim((string) ($record['external_user_id'] ?? $record['student_id'] ?? $record['employee_id'] ?? ''));
        $firstName = trim((string) ($record['first_name'] ?? ''));
        $lastName = trim((string) ($record['last_name'] ?? ''));
        $email = trim((string) ($record['email'] ?? ''));
        $status = trim((string) ($record['status'] ?? 'active')) ?: 'active';
        $tokens = (int) ($record['tokens'] ?? $record['hour_balance'] ?? 0);
        $assignedAreaId = trim((string) ($record['assigned_area_id'] ?? ''));
        $password = trim((string) ($record['password'] ?? ''));
        $userTypeId = $this->resolveImportUserTypeId($record);

        if ($firstName === '' || $lastName === '') {
            return [
                'success' => false,
                'message' => 'Row ' . $rowNumber . ': first_name and last_name are required.'
            ];
        }

        if ($userTypeId === null) {
            return [
                'success' => false,
                'message' => 'Row ' . $rowNumber . ': user_type_id or role is required.'
            ];
        }

        if ($email === '') {
            $email = $this->buildFoundationEmail($firstName, $lastName, $externalUserId);
        }

        if ($email === '') {
            return [
                'success' => false,
                'message' => 'Row ' . $rowNumber . ': could not generate an email address.'
            ];
        }

        $existingUser = null;
        if ($externalUserId !== '') {
            $existingUser = $this->userModel->getUserByExternalId($externalUserId);
        }

        if (!$existingUser) {
            $existingUser = $this->userModel->where('email', $email)->first();
        }

        $payload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'user_type_id' => $userTypeId,
            'tokens' => $tokens,
            'status' => in_array($status, ['active', 'inactive', 'suspended'], true) ? $status : 'active',
        ];

        if ($externalUserId !== '') {
            $payload['external_user_id'] = $externalUserId;
        } elseif ($existingUser && !empty($existingUser['external_user_id'])) {
            $payload['external_user_id'] = $existingUser['external_user_id'];
        }

        if ($assignedAreaId !== '') {
            $payload['assigned_area_id'] = (int) $assignedAreaId;
        }

        if ($existingUser) {
            if ($password !== '') {
                $payload['password'] = $password;
            }

            $updated = $this->userModel->updateUser($existingUser['user_id'], $payload);
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Row ' . $rowNumber . ': failed to update existing user.'
                ];
            }

            log_update('User', $existingUser['user_id'], $firstName . ' ' . $lastName);

            return [
                'success' => true,
                'action' => 'updated'
            ];
        }

        if ($password === '') {
            $password = $this->generateTemporaryPassword($externalUserId !== '' ? $externalUserId : $email);
        }

        $payload['password'] = $password;

        $userId = $this->userModel->createUser($payload);
        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Row ' . $rowNumber . ': failed to create user.'
            ];
        }

        log_create('User', $userId, $firstName . ' ' . $lastName);

        return [
            'success' => true,
            'action' => 'created'
        ];
    }

    /**
     * Resolve a user type from imported data.
     */
    private function resolveImportUserTypeId(array $record): ?int
    {
        $userTypeId = trim((string) ($record['user_type_id'] ?? ''));
        if ($userTypeId !== '' && ctype_digit($userTypeId)) {
            return (int) $userTypeId;
        }

        $role = strtolower(trim((string) ($record['role'] ?? $record['user_type_name'] ?? '')));
        if ($role === '') {
            return null;
        }

        return match ($role) {
            'subscriber', 'subscribers', 'student', 'students', 'user', 'users' => UserModel::ROLE_SUBSCRIBER,
            'attendant', 'attendants', 'staff' => UserModel::ROLE_ATTENDANT,
            'admin', 'administrator', 'administrators' => UserModel::ROLE_ADMIN,
            default => null,
        };
    }

    // ====================================
    // STAFF MANAGEMENT METHODS
    // ====================================
    
    /**
     * Get staff list (admins and attendants) - AJAX endpoint
     */
    public function getStaffList()
    {
        // Get filters from request
        $filters = [
            'search' => $this->request->getGet('search'),
            'user_type_id' => $this->request->getGet('user_type_id'),
            'status' => $this->request->getGet('status'),
            'is_online' => $this->request->getGet('is_online'),
            'assigned_area_id' => $this->request->getGet('assigned_area_id'),
            'attendant_only' => true // Special flag to only show admin/attendant
        ];

        // Get pagination params
        $page = $this->request->getGet('page') ?? 1;
        $perPage = $this->request->getGet('per_page') ?? 25;
        $offset = ($page - 1) * $perPage;

        // Get sort parameters
        $sortColumn = $this->request->getGet('sort_column') ?? 'user_id';
        $sortDirection = $this->request->getGet('sort_direction') ?? 'DESC';

        // Get staff members
        $staff = $this->userModel->getAttendantsWithFilters($filters, $perPage, $offset, $sortColumn, $sortDirection);
        $totalStaff = $this->userModel->getAttendantsCount($filters);

        // Calculate pagination info
        $totalPages = ceil($totalStaff / $perPage);
        $showingFrom = $totalStaff > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $showingTo = min($page * $perPage, $totalStaff);

        // Get staff stats
        $stats = $this->userModel->getAttendantStats();

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'data' => $staff,
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => $totalStaff,
                'from' => $showingFrom,
                'to' => $showingTo
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Get staff user types (roles) for filter dropdown
     */
    public function getStaffUserTypes()
    {
        try {
            $types = $this->userModel->getAttendantUserTypes();
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $types
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getStaffUserTypes: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Get parking areas for filter dropdown
     */
    public function getParkingAreas()
    {
        try {
            $areas = $this->userModel->getParkingAreas();
            
            return $this->response->setJSON([
                'success' => true,
                'data' => $areas
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in getParkingAreas: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Get single staff member details - AJAX endpoint
     */
    public function getStaffDetails($userId)
    {
        try {
            $user = $this->userModel->getUserById($userId);

            if (!$user) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Staff member not found'
                ])->setStatusCode(404);
            }

            // Verify user is staff (admin = 3, attendants = 2)
            if (!in_array($user['user_type_id'], [2, 3])) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'User is not a staff member'
                ])->setStatusCode(403);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error fetching staff details: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error loading staff details'
            ])->setStatusCode(500);
        }
    }

    /**
     * Export staff to CSV
     */
    public function exportStaff()
    {
        // Get filters from request
        $filters = [
            'search' => $this->request->getGet('search'),
            'user_type_id' => $this->request->getGet('user_type_id'),
            'status' => $this->request->getGet('status'),
            'is_online' => $this->request->getGet('is_online'),
            'assigned_area_id' => $this->request->getGet('assigned_area_id'),
            'attendant_only' => true
        ];

        $staff = $this->userModel->getAllAttendants($filters);

        // Generate CSV
        $filename = 'staff_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps Excel recognize UTF-8)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'User ID',
            'External ID',
            'First Name',
            'Last Name',
            'Email',
            'Role',
            'Assigned Area',
            'Status',
            'Online Status',
            'Created At'
        ]);
        
        // CSV Data
        foreach ($staff as $member) {
            // Format created_at safely
            $createdAt = '';
            if (!empty($member['created_at'])) {
                $timestamp = strtotime($member['created_at']);
                if ($timestamp !== false) {
                    $createdAt = date('Y-m-d H:i:s', $timestamp);
                } else {
                    $createdAt = $member['created_at'];
                }
            }
            
            fputcsv($output, [
                $member['user_id'] ?? '',
                $member['external_user_id'] ?? '',
                $member['first_name'] ?? '',
                $member['last_name'] ?? '',
                $member['email'] ?? '',
                $member['account_type_name'] ?? $member['user_type_name'] ?? 'N/A',
                $member['parking_area_name'] ?? 'Not Assigned',
                ucfirst($member['status'] ?? 'N/A'),
                (!empty($member['is_online']) && $member['is_online']) ? 'Online' : 'Offline',
                $createdAt
            ]);
        }
        
        fclose($output);
        exit;
    }
}



