<?php

namespace App\Controllers;

class LayoutCustomElements extends BaseController
{
    private const ALLOWED_CATEGORIES = ['road', 'obstacle'];
    private const ALLOWED_ICON_TYPES = ['fa', 'text', 'svg', 'image'];
    private const ALLOWED_PLACEMENT_MODES = ['single', 'fill-horizontal', 'fill-vertical'];

    public function index()
    {
        $settings = $this->readSettings();
        return $this->response->setJSON([
            'success' => true,
            'elements' => $settings['elements'] ?? [],
            'hidden_elements' => $settings['hidden_elements'] ?? [],
        ]);
    }

    public function save()
    {
        $json = $this->request->getJSON(true);
        $elements = is_array($json['elements'] ?? null) ? $json['elements'] : [];
        $hiddenElements = is_array($json['hidden_elements'] ?? null) ? $json['hidden_elements'] : [];
        $clean = [];

        foreach ($elements as $id => $element) {
            if (!is_array($element)) {
                continue;
            }

            $safe = $this->sanitizeElement($id, $element);
            if ($safe !== null) {
                $clean[$safe['id']] = $safe;
            }
        }

        $cleanHidden = [];
        foreach ($hiddenElements as $type) {
            $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $type);
            if ($safeType !== '') {
                $cleanHidden[] = $safeType;
            }
        }

        $this->writeSettings([
            'elements' => $clean,
            'hidden_elements' => array_values(array_unique($cleanHidden)),
        ]);

        return $this->response->setJSON([
            'success' => true,
            'elements' => $clean,
            'hidden_elements' => array_values(array_unique($cleanHidden)),
        ]);
    }

    private function sanitizeElement(string $id, array $element): ?array
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($element['id'] ?? $id));
        if ($safeId === '' || strpos($safeId, 'custom-') !== 0) {
            return null;
        }

        $category = (string) ($element['category'] ?? 'road');
        $iconType = (string) ($element['iconType'] ?? 'text');
        $placementMode = (string) ($element['placementMode'] ?? 'single');

        return [
            'id' => $safeId,
            'name' => substr(strip_tags((string) ($element['name'] ?? 'Custom Element')), 0, 32),
            'category' => in_array($category, self::ALLOWED_CATEGORIES, true) ? $category : 'road',
            'iconType' => in_array($iconType, self::ALLOWED_ICON_TYPES, true) ? $iconType : 'text',
            'iconValue' => substr((string) ($element['iconValue'] ?? ''), 0, 200000),
            'placementMode' => in_array($placementMode, self::ALLOWED_PLACEMENT_MODES, true) ? $placementMode : 'single',
        ];
    }

    private function path(): string
    {
        return WRITEPATH . 'config/layout_custom_elements.json';
    }

    private function readSettings(): array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return ['elements' => [], 'hidden_elements' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return ['elements' => [], 'hidden_elements' => []];
        }

        if (isset($decoded['elements']) || isset($decoded['hidden_elements'])) {
            return [
                'elements' => is_array($decoded['elements'] ?? null) ? $decoded['elements'] : [],
                'hidden_elements' => is_array($decoded['hidden_elements'] ?? null) ? $decoded['hidden_elements'] : [],
            ];
        }

        return ['elements' => $decoded, 'hidden_elements' => []];
    }

    private function writeSettings(array $settings): void
    {
        $path = $this->path();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
