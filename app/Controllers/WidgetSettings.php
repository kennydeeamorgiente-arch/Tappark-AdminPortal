<?php

namespace App\Controllers;

class WidgetSettings extends BaseController
{
    private const ALLOWED_PAGES = ['dashboard', 'reports'];
    private const ALLOWED_SOURCES = ['Revenue', 'Bookings', 'Users', 'Occupancy', 'Feedback', 'Subscriptions', 'Guest bookings'];
    private const ALLOWED_SIZES = ['small', 'medium', 'wide', 'full'];
    private const ALLOWED_CHARTS = ['', 'line', 'bar', 'pie', 'doughnut'];

    public function get(string $page)
    {
        if (!in_array($page, self::ALLOWED_PAGES, true)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Unknown settings page']);
        }

        return $this->response->setJSON([
            'success' => true,
            'settings' => $this->readAll()[$page] ?? []
        ]);
    }

    public function save(string $page)
    {
        if (!in_array($page, self::ALLOWED_PAGES, true)) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Unknown settings page']);
        }

        $json = $this->request->getJSON(true);
        $settings = is_array($json['settings'] ?? null) ? $json['settings'] : [];

        $all = $this->readAll();
        $all[$page] = $this->sanitizeSettings($settings);
        $path = $this->settingsPath();

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->response->setJSON([
            'success' => true,
            'settings' => $all[$page]
        ]);
    }

    private function settingsPath(): string
    {
        return WRITEPATH . 'config/widget_settings.json';
    }

    private function readAll(): array
    {
        $path = $this->settingsPath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sanitizeSettings(array $settings): array
    {
        $clean = [];
        foreach ($settings as $id => $setting) {
            if (!is_array($setting)) {
                continue;
            }

            $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $id);
            if ($safeId === '') {
                continue;
            }

            $dataSource = (string) ($setting['dataSource'] ?? 'Bookings');
            $size = (string) ($setting['size'] ?? 'medium');
            $chartType = (string) ($setting['chartType'] ?? '');

            $clean[$safeId] = [
                'visible' => (bool) ($setting['visible'] ?? true),
                'order' => max(1, min(999, (int) ($setting['order'] ?? 1))),
                'title' => substr(strip_tags((string) ($setting['title'] ?? '')), 0, 80),
                'subtitle' => substr(strip_tags((string) ($setting['subtitle'] ?? '')), 0, 140),
                'icon' => substr(preg_replace('/[^a-zA-Z0-9_ -]/', '', (string) ($setting['icon'] ?? 'fas fa-chart-simple')), 0, 80),
                'accent' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($setting['accent'] ?? '')) ? $setting['accent'] : '#8b1f2b',
                'size' => in_array($size, self::ALLOWED_SIZES, true) ? $size : 'medium',
                'chartType' => in_array($chartType, self::ALLOWED_CHARTS, true) ? $chartType : '',
                'dataSource' => in_array($dataSource, self::ALLOWED_SOURCES, true) ? $dataSource : 'Bookings',
                'exportVisible' => (bool) ($setting['exportVisible'] ?? true),
                'sectionCollapsed' => (bool) ($setting['sectionCollapsed'] ?? false),
            ];
        }

        return $clean;
    }
}
