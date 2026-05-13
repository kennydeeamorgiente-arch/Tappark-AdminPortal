<?php

namespace App\Filters;

use App\Models\UserModel;
use App\Models\SystemSettingsModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');

        $userId = $session->get('user_id');
        $userTypeId = $session->get('user_type_id');
        $accessLevel = $session->get('access_level');

        if (!$userId || ((int) $accessLevel !== UserModel::ROLE_ADMIN && (int) $userTypeId !== UserModel::ROLE_ADMIN)) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Unauthorized access. Please log in again.'
                    ]);
            }

            return redirect()->to('/login');
        }

        // Apply global settings from session
        $appSettings = $session->get('app_settings');
        if (!$appSettings) {
            try {
                $settingsModel = new SystemSettingsModel();
                $appSettings = $settingsModel->getGroupSettings('application');
                if (!empty($appSettings)) {
                    $session->set(['app_settings' => $appSettings]);
                }
            } catch (\Throwable $e) {
                log_message('error', 'AdminAuthFilter settings load failed: ' . $e->getMessage());
            }
        }

        if ($appSettings) {
            // Apply timezone
            if (!empty($appSettings['timezone'])) {
                date_default_timezone_set($appSettings['timezone']);
            }

            // Apply session expiration dynamically (convert minutes to seconds)
            if (!empty($appSettings['session_timeout'])) {
                $expiration = (int)$appSettings['session_timeout'] * 60;
                $sessionConfig = config('Session');
                $sessionConfig->expiration = $expiration;
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
