(function () {
    'use strict';

    const PREFIX = 'custom-';
    const BUILT_IN_ELEMENTS = [
        { id: 'road', name: 'Road', category: 'road' },
        { id: 'l-road', name: 'L-Road', category: 'road' },
        { id: 'intersection', name: 'Intersection', category: 'road' },
        { id: 't-road', name: 'T-Road', category: 'road' },
        { id: 'entrance', name: 'Entrance', category: 'road' },
        { id: 'exit', name: 'Exit', category: 'road' },
        { id: 'oneway', name: 'One Way', category: 'road' },
        { id: 'two-way', name: 'Two Way', category: 'road' },
        { id: 'entry-exit', name: 'Entry/Exit', category: 'road' },
        { id: 'wall', name: 'Wall', category: 'obstacle' },
        { id: 'pillar', name: 'Pillar', category: 'obstacle' },
        { id: 'tree', name: 'Tree', category: 'obstacle' }
    ];

    function baseUrl() {
        return (window.APP_BASE_URL || window.BASE_URL || '/');
    }

    function apiUrl() {
        return `${baseUrl()}api/layout-custom-elements`;
    }

    function csrfHeaders() {
        const token = typeof window.getCSRFToken === 'function' ? window.getCSRFToken() : '';
        return token ? { 'X-CSRF-TOKEN': token } : {};
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    }

    function elementId(name) {
        const slug = String(name || 'element').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'element';
        return `${PREFIX}${slug}-${Date.now().toString(36)}`;
    }

    function readUpload(file) {
        return new Promise(resolve => {
            if (!file) {
                resolve('');
                return;
            }
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => resolve('');
            reader.readAsDataURL(file);
        });
    }

    async function loadElements() {
        try {
            const response = await fetch(apiUrl(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = response.ok ? await response.json() : null;
            window.customLayoutElements = result?.success && result.elements ? result.elements : {};
            window.hiddenLayoutElements = result?.success && Array.isArray(result.hidden_elements) ? result.hidden_elements : [];
        } catch (error) {
            window.customLayoutElements = window.customLayoutElements || {};
            window.hiddenLayoutElements = window.hiddenLayoutElements || [];
        }
        renderList();
        return window.customLayoutElements;
    }

    async function saveElements() {
        await fetch(apiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders()
            },
            body: JSON.stringify({
                elements: window.customLayoutElements || {},
                hidden_elements: window.hiddenLayoutElements || []
            })
        });
    }

    function notify(message, type = 'success') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            alert(message);
        }
    }

    function renderList() {
        const list = document.getElementById('systemLayoutElementList');
        if (!list) return;
        const hidden = new Set(window.hiddenLayoutElements || []);
        const builtIns = BUILT_IN_ELEMENTS.map(element => ({ ...element, builtin: true }));
        const custom = Object.values(window.customLayoutElements || {}).map(element => ({ ...element, builtin: false }));
        const elements = [...builtIns, ...custom];

        list.innerHTML = elements.map(element => `
            <div class="system-layout-element-item">
                <div>
                    <h6 class="mb-1 fw-semibold">${escapeHtml(element.name)}</h6>
                    <div class="system-layout-element-meta">
                        ${escapeHtml(element.category === 'road' ? 'Road Element' : 'Obstacle Element')} ·
                        ${element.builtin ? 'Built-in' : `${escapeHtml(element.iconType)} · ${escapeHtml(element.placementMode || 'single')}`} ·
                        ${hidden.has(element.id) ? 'Hidden' : 'Visible'}
                    </div>
                </div>
                <div class="system-layout-element-actions">
                    <button type="button" class="btn btn-outline-${hidden.has(element.id) ? 'success' : 'secondary'} btn-sm" data-toggle-system-layout-element="${escapeHtml(element.id)}">
                        <i class="fas ${hidden.has(element.id) ? 'fa-eye' : 'fa-eye-slash'} me-1"></i>${hidden.has(element.id) ? 'Show' : 'Hide'}
                    </button>
                    ${element.builtin ? '' : `
                        <button type="button" class="btn btn-outline-primary btn-sm" data-edit-system-layout-element="${escapeHtml(element.id)}">
                            <i class="fas fa-pen me-1"></i>Edit
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-system-layout-element="${escapeHtml(element.id)}">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    `}
                </div>
            </div>
        `).join('');
    }

    async function saveElementFromForm() {
        const editingId = String(document.getElementById('systemCustomElementEditingId')?.value || '').trim();
        const name = String(document.getElementById('systemCustomElementName')?.value || '').trim().substring(0, 32);
        const category = document.getElementById('systemCustomElementCategory')?.value === 'obstacle' ? 'obstacle' : 'road';
        const iconType = document.getElementById('systemCustomElementIconType')?.value || 'text';
        const placementMode = document.getElementById('systemCustomElementPlacementMode')?.value || 'single';
        const upload = document.getElementById('systemCustomElementImageUpload')?.files?.[0] || null;
        const uploadedValue = iconType === 'image' ? await readUpload(upload) : '';
        const currentElement = editingId ? (window.customLayoutElements || {})[editingId] : null;
        const iconValue = (uploadedValue || String(document.getElementById('systemCustomElementIconValue')?.value || '').trim() || currentElement?.iconValue || '').substring(0, 200000);

        if (!name || !iconValue) {
            window.showToast?.('Custom elements need a name and icon/content.', 'error');
            return;
        }

        const id = editingId || elementId(name);
        window.customLayoutElements = window.customLayoutElements || {};
        window.customLayoutElements[id] = { id, name, category, iconType, iconValue, placementMode };
        window.hiddenLayoutElements = (window.hiddenLayoutElements || []).filter(type => type !== id);
        await saveElements();
        clearForm();
        renderList();
        notify(editingId ? 'System layout element updated successfully.' : 'System layout element saved successfully.', 'success');
    }

    function clearForm() {
        ['systemCustomElementName', 'systemCustomElementIconValue', 'systemCustomElementImageUpload'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const editingInput = document.getElementById('systemCustomElementEditingId');
        if (editingInput) editingInput.value = '';
        const saveText = document.querySelector('#saveSystemLayoutElementBtn span');
        if (saveText) saveText.textContent = 'Save Element';
        document.getElementById('cancelSystemLayoutElementEditBtn')?.classList.add('d-none');
    }

    function openForm() {
        const form = document.getElementById('systemLayoutElementForm');
        const button = document.getElementById('addSystemLayoutElementBtn');
        form?.classList.remove('d-none');
        button?.setAttribute('aria-expanded', 'true');
        const icon = button?.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        }
    }

    function editElement(id) {
        const element = (window.customLayoutElements || {})[id];
        if (!element) return;
        openForm();
        document.getElementById('systemCustomElementEditingId').value = element.id;
        document.getElementById('systemCustomElementName').value = element.name || '';
        document.getElementById('systemCustomElementCategory').value = element.category || 'road';
        document.getElementById('systemCustomElementIconType').value = element.iconType || 'text';
        document.getElementById('systemCustomElementPlacementMode').value = element.placementMode || 'single';
        document.getElementById('systemCustomElementIconValue').value = element.iconValue || '';
        const saveText = document.querySelector('#saveSystemLayoutElementBtn span');
        if (saveText) saveText.textContent = 'Update Element';
        document.getElementById('cancelSystemLayoutElementEditBtn')?.classList.remove('d-none');
    }

    document.addEventListener('click', async (event) => {
        const addBtn = event.target.closest('#addSystemLayoutElementBtn');
        if (addBtn) {
            const form = document.getElementById('systemLayoutElementForm');
            form?.classList.toggle('d-none');
            const expanded = form ? !form.classList.contains('d-none') : false;
            addBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            const icon = addBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-chevron-down', !expanded);
                icon.classList.toggle('fa-chevron-up', expanded);
            }
            return;
        }

        if (event.target.closest('#saveSystemLayoutElementBtn')) {
            await saveElementFromForm();
            return;
        }

        if (event.target.closest('#cancelSystemLayoutElementEditBtn')) {
            clearForm();
            return;
        }

        const editBtn = event.target.closest('[data-edit-system-layout-element]');
        if (editBtn) {
            editElement(editBtn.getAttribute('data-edit-system-layout-element'));
            return;
        }

        const toggleBtn = event.target.closest('[data-toggle-system-layout-element]');
        if (toggleBtn) {
            const id = toggleBtn.getAttribute('data-toggle-system-layout-element');
            const hidden = new Set(window.hiddenLayoutElements || []);
            if (hidden.has(id)) {
                hidden.delete(id);
                notify('Layout element is visible again.', 'success');
            } else {
                hidden.add(id);
                notify('Layout element hidden from designer palette.', 'success');
            }
            window.hiddenLayoutElements = Array.from(hidden);
            await saveElements();
            renderList();
            return;
        }

        const removeBtn = event.target.closest('[data-remove-system-layout-element]');
        if (removeBtn) {
            const id = removeBtn.getAttribute('data-remove-system-layout-element');
            delete (window.customLayoutElements || {})[id];
            window.hiddenLayoutElements = (window.hiddenLayoutElements || []).filter(type => type !== id);
            await saveElements();
            renderList();
            notify('System layout element removed successfully.', 'success');
        }
    });

    document.addEventListener('shown.bs.modal', (event) => {
        if (event.target?.id === 'profileModal') {
            loadElements();
        }
    });

    window.loadSystemLayoutCustomElements = loadElements;
})();
