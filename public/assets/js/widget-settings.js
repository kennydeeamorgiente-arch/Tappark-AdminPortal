(function () {
    'use strict';

    const APPROVED_SOURCES = ['Revenue', 'Bookings', 'Users', 'Occupancy', 'Feedback', 'Subscriptions', 'Guest bookings'];
    const SIZE_CLASS = {
        small: 'col-xl-2 col-lg-3 col-md-4 col-sm-6',
        medium: 'col-lg-6 col-md-6',
        wide: 'col-lg-8 col-md-12',
        full: 'col-12'
    };
    const CHART_TYPES = ['line', 'bar', 'pie', 'doughnut'];
    const STORAGE_PREFIX = 'tappark_widget_settings_';
    const STATIC_WIDGETS = {
        dashboard: [
            ['dashboard-stat-total-subscribers', 'stat', 'Total Subscribers', 'Active and inactive subscribers', 'Users'],
            ['dashboard-stat-active-bookings', 'stat', 'Active Bookings', 'Current active bookings', 'Bookings'],
            ['dashboard-stat-parking-spaces', 'stat', 'Parking Spaces', 'Available parking spaces', 'Occupancy'],
            ['dashboard-stat-revenue', 'stat', 'Revenue', 'Earnings for selected dates', 'Revenue'],
            ['dashboard-stat-occupancy', 'stat', 'Occupancy', 'Parking occupancy rate', 'Occupancy'],
            ['dashboard-stat-online-staff', 'stat', 'Online Staff', 'Staff currently online', 'Users'],
            ['dashboard-chart-revenuechart', 'chart', 'Revenue Trend', 'Money earned for selected dates', 'Revenue', 'revenueChart'],
            ['dashboard-chart-occupancychart', 'chart', 'Parking Occupancy', 'Occupancy chart', 'Occupancy', 'occupancyChart'],
            ['dashboard-chart-bookingschart', 'chart', 'Bookings Overview', 'Bookings for selected dates', 'Bookings', 'bookingsChart'],
            ['dashboard-chart-usergrowthchart', 'chart', 'Subscriber Growth', 'Subscriber growth over time', 'Users', 'userGrowthChart'],
            ['dashboard-chart-hourbalancechart', 'chart', 'Token Balance Trends', 'Purchased, used, and remaining tokens', 'Revenue', 'hourBalanceChart'],
            ['dashboard-chart-avgratingchart', 'chart', 'Average Rating Over Time', 'Feedback ratings for selected dates', 'Feedback', 'avgRatingChart'],
            ['dashboard-chart-guestbookingstrendchart', 'chart', 'Guest Bookings Summary', 'Guest bookings over time and by vehicle type', 'Guest bookings', 'guestBookingsTrendChart']
        ],
        reports: [
            ['reports-stat-activity-rate', 'stat', 'Activity Rate', 'Active users in selected range', 'Users'],
            ['reports-stat-avg-duration', 'stat', 'Avg Duration', 'Hours per session', 'Bookings'],
            ['reports-stat-active-subscriptions', 'stat', 'Active Subscriptions', 'Subscription totals', 'Subscriptions'],
            ['reports-stat-avg-revenue', 'stat', 'Avg Revenue', 'Average revenue per booking', 'Revenue'],
            ['reports-stat-cancellation', 'stat', 'Cancellation', 'Cancelled bookings rate', 'Bookings'],
            ['reports-stat-repeat-rate', 'stat', 'Repeat Rate', 'Customer loyalty', 'Bookings'],
            ['reports-chart-revenueplanchart', 'chart', 'Revenue by Plan', 'Bookings split by subscription plan', 'Revenue', 'revenuePlanChart'],
            ['reports-chart-revenuehourchart', 'chart', 'Revenue by Hour', 'Revenue grouped by hour', 'Revenue', 'revenueHourChart'],
            ['reports-chart-revenuedaychart', 'chart', 'Revenue by Day', 'Revenue grouped by day', 'Revenue', 'revenueDayChart'],
            ['reports-chart-revenueareachart', 'chart', 'Revenue by Area', 'Revenue grouped by parking area', 'Revenue', 'revenueAreaChart'],
            ['reports-chart-revenuetrendchart', 'chart', 'Revenue Growth', 'Revenue trend for selected range', 'Revenue', 'revenueTrendChart'],
            ['reports-chart-bookingsdaychart', 'chart', 'Bookings by Day', 'Bookings grouped by day', 'Bookings', 'bookingsDayChart'],
            ['reports-chart-peakhourschart', 'chart', 'Peak Hours', 'Booking demand by hour', 'Bookings', 'peakHoursChart'],
            ['reports-chart-bookingsareachart', 'chart', 'Bookings by Area', 'Bookings grouped by parking area', 'Bookings', 'bookingsAreaChart'],
            ['reports-chart-vehicletypeschart', 'chart', 'Vehicle Types', 'Bookings grouped by vehicle type', 'Bookings', 'vehicleTypesChart'],
            ['reports-chart-hourlyoccupancychart', 'chart', 'Hourly Occupancy', 'Occupancy by hour', 'Occupancy', 'hourlyOccupancyChart'],
            ['reports-chart-usergrowthchart', 'chart', 'User Growth', 'User growth over time', 'Users', 'userGrowthChart'],
            ['reports-chart-useractivitychart', 'chart', 'User Activity', 'User activity by period', 'Users', 'userActivityChart'],
            ['reports-chart-subscriptionchart', 'chart', 'Subscription Distribution', 'Subscription plan distribution', 'Subscriptions', 'subscriptionChart'],
            ['reports-chart-bookingstatuschart', 'chart', 'Booking Status', 'Booking status breakdown', 'Bookings', 'bookingStatusChart'],
            ['reports-chart-reportsguestbookingstrendchart', 'chart', 'Guest Bookings Trend', 'Guest bookings over time', 'Guest bookings', 'reportsGuestBookingsTrendChart'],
            ['reports-chart-reportsguestbookingsvehiclechart', 'chart', 'Guest Bookings Vehicle', 'Guest bookings by vehicle', 'Guest bookings', 'reportsGuestBookingsVehicleChart'],
            ['reports-chart-reportsguestbookingsattendantchart', 'chart', 'Guest Bookings Attendant', 'Guest bookings by attendant', 'Guest bookings', 'reportsGuestBookingsAttendantChart'],
            ['reports-chart-feedbackratingchart', 'chart', 'Feedback Ratings', 'Feedback ratings breakdown', 'Feedback', 'feedbackRatingChart']
        ]
    };

    function pageKey() {
        if (document.getElementById('dashboardContent')) return 'dashboard';
        if (document.getElementById('reportsContent')) return 'reports';
        return null;
    }

    function storageKey(page) {
        return STORAGE_PREFIX + page;
    }

    function readSettings(page) {
        try {
            const parsed = JSON.parse(localStorage.getItem(storageKey(page)) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function writeSettings(page, settings) {
        localStorage.setItem(storageKey(page), JSON.stringify(settings || {}));
    }

    function serverUrl(page) {
        const base = (typeof BASE_URL !== 'undefined') ? BASE_URL : (window.BASE_URL || '/');
        return `${base}api/widget-settings/${page}`;
    }

    function csrfHeaders() {
        const token = typeof window.getCSRFToken === 'function' ? window.getCSRFToken() : '';
        return token ? { 'X-CSRF-TOKEN': token } : {};
    }

    function loadServerSettings(page) {
        return fetch(serverUrl(page), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.ok ? response.json() : null)
            .then(result => {
                if (result?.success && result.settings) {
                    const merged = { ...readSettings(page), ...result.settings };
                    writeSettings(page, merged);
                    return merged;
                }
                return readSettings(page);
            })
            .catch(() => readSettings(page));
    }

    function saveServerSettings(page, settings) {
        return fetch(serverUrl(page), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders()
            },
            body: JSON.stringify({ settings })
        }).catch(() => null);
    }

    function notifyWidgetSettingsSaved(page) {
        const label = page === 'reports' ? 'Reports' : 'Dashboard';
        const message = `${label} widget settings saved successfully.`;
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'success');
        } else {
            alert(message);
        }
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

    function normalizeId(value) {
        return String(value || 'widget').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function titleFromCard(card) {
        return card?.querySelector('h4,h5,h6')?.textContent?.trim() || 'Widget';
    }

    function subtitleFromCard(card) {
        return card?.querySelector('small,.text-muted,p')?.textContent?.trim() || '';
    }

    function iconFromCard(card) {
        const icon = card?.querySelector('h4 i,h5 i,h6 i,.stats-icon i,small i');
        return icon ? Array.from(icon.classList).filter(cls => cls !== 'me-1' && cls !== 'me-2' && cls !== 'text-primary' && !cls.startsWith('fa-2x')).join(' ') : 'fas fa-chart-simple';
    }

    function inferSource(id, title) {
        const text = `${id} ${title}`.toLowerCase();
        if (text.includes('revenue') || text.includes('token')) return 'Revenue';
        if (text.includes('booking')) return text.includes('guest') ? 'Guest bookings' : 'Bookings';
        if (text.includes('user') || text.includes('subscriber') || text.includes('staff')) return 'Users';
        if (text.includes('occupancy') || text.includes('parking') || text.includes('space')) return 'Occupancy';
        if (text.includes('rating') || text.includes('feedback')) return 'Feedback';
        if (text.includes('subscription') || text.includes('plan')) return 'Subscriptions';
        return 'Bookings';
    }

    function chartIdFor(card) {
        return card?.querySelector('canvas')?.id || '';
    }

    function widgetElementFromCard(card) {
        return card?.closest('[class*="col-"]') || card;
    }

    function collectDashboardWidgets() {
        const root = document.getElementById('dashboardContent');
        if (!root) return [];
        const widgets = [];

        root.querySelectorAll('.stats-card-modern').forEach((card, index) => {
            const title = titleFromCard(card);
            widgets.push({
                id: 'dashboard-stat-' + normalizeId(title || index),
                type: 'stat',
                title,
                subtitle: subtitleFromCard(card),
                source: inferSource(title, title),
                element: widgetElementFromCard(card),
                card
            });
        });

        root.querySelectorAll('.dashboard-chart-card,.guest-summary-card').forEach((card) => {
            const title = titleFromCard(card);
            const canvasId = chartIdFor(card);
            widgets.push({
                id: 'dashboard-chart-' + normalizeId(canvasId || title),
                type: 'chart',
                title,
                subtitle: subtitleFromCard(card),
                source: inferSource(canvasId, title),
                chartId: canvasId,
                element: widgetElementFromCard(card),
                card
            });
        });

        return widgets;
    }

    function collectReportWidgets() {
        const root = document.getElementById('reportsContent');
        if (!root) return [];
        const widgets = [];

        root.querySelectorAll('.stats-card-modern').forEach((card, index) => {
            const title = titleFromCard(card);
            widgets.push({
                id: 'reports-stat-' + normalizeId(title || index),
                type: 'stat',
                title,
                subtitle: subtitleFromCard(card),
                source: inferSource(title, title),
                element: widgetElementFromCard(card),
                card
            });
        });

        root.querySelectorAll('canvas').forEach((canvas) => {
            const card = canvas.closest('.card');
            if (!card) return;
            const title = titleFromCard(card);
            widgets.push({
                id: 'reports-chart-' + normalizeId(canvas.id || title),
                type: 'chart',
                title,
                subtitle: subtitleFromCard(card),
                source: inferSource(canvas.id, title),
                chartId: canvas.id,
                element: widgetElementFromCard(card),
                card
            });
        });

        root.querySelectorAll('#reportsContent > .row > .col-12 > .card').forEach((card) => {
            const title = titleFromCard(card);
            const id = 'reports-section-' + normalizeId(title);
            if (!widgets.some(widget => widget.id === id)) {
                widgets.push({
                    id,
                    type: 'section',
                    title,
                    subtitle: subtitleFromCard(card),
                    source: inferSource(title, title),
                    element: widgetElementFromCard(card),
                    card
                });
            }
        });

        return widgets;
    }

    function collectWidgets(page) {
        const liveWidgets = page === 'dashboard' ? collectDashboardWidgets() : collectReportWidgets();
        if (liveWidgets.length) return liveWidgets;
        return (STATIC_WIDGETS[page] || []).map((item, index) => ({
            id: item[0],
            type: item[1],
            title: item[2],
            subtitle: item[3],
            source: item[4],
            chartId: item[5] || '',
            icon: item[1] === 'chart' ? 'fas fa-chart-line' : 'fas fa-square-poll-vertical',
            element: null,
            card: null,
            staticOrder: index + 1
        }));
    }

    function ensureDefaults(page, widgets) {
        const settings = readSettings(page);
        widgets.forEach((widget, index) => {
            settings[widget.id] = {
                visible: true,
                order: widget.staticOrder || index + 1,
                title: widget.title,
                subtitle: widget.subtitle,
                icon: widget.icon || iconFromCard(widget.card),
                accent: '#8b1f2b',
                size: widget.type === 'section' ? 'full' : (widget.type === 'chart' ? 'medium' : 'small'),
                chartType: '',
                dataSource: widget.source,
                exportVisible: true,
                ...(settings[widget.id] || {})
            };
        });
        writeSettings(page, settings);
        return settings;
    }

    function applySettingsToWidget(widget, setting) {
        const target = widget.element;
        if (!target || !setting) return;
        target.dataset.widgetId = widget.id;
        target.style.order = parseInt(setting.order, 10) || 0;
        target.classList.toggle('d-none', setting.visible === false);
        target.classList.toggle('widget-export-hidden', setting.exportVisible === false || setting.visible === false);

        Object.values(SIZE_CLASS).forEach(classes => classes.split(' ').forEach(cls => target.classList.remove(cls)));
        (SIZE_CLASS[setting.size] || SIZE_CLASS.medium).split(' ').forEach(cls => target.classList.add(cls));

        const titleEl = widget.card.querySelector('h4,h5,h6');
        if (titleEl && setting.title) {
            const iconEl = titleEl.querySelector('i');
            titleEl.textContent = '';
            if (iconEl) {
                iconEl.className = `${setting.icon || widget.icon || iconFromCard(widget.card)} me-2`;
                iconEl.style.color = setting.accent || '';
                titleEl.appendChild(iconEl);
                titleEl.appendChild(document.createTextNode(setting.title));
            } else {
                titleEl.textContent = setting.title;
            }
        }

        const subtitleEl = widget.card.querySelector('.card-header small,.guest-summary-header__meta,small.text-muted');
        if (subtitleEl && typeof setting.subtitle === 'string') {
            subtitleEl.textContent = setting.subtitle;
        }

        widget.card.style.setProperty('--widget-accent', setting.accent || '#8b1f2b');
        widget.card.style.borderTop = `3px solid ${setting.accent || '#8b1f2b'}`;
        applyChartType(widget.chartId, setting.chartType);
    }

    function applyChartType(chartId, chartType) {
        if (!chartId || !CHART_TYPES.includes(chartType)) return;
        const charts = { ...(window.dashboardCharts || {}), ...(window.reportsCharts || {}) };
        const chart = Object.values(charts).find(item => item?.canvas?.id === chartId);
        if (!chart || chart.config.type === chartType) return;
        if ((chartType === 'pie' || chartType === 'doughnut') && chart.data?.datasets?.length > 1) return;
        chart.config.type = chartType;
        chart.update();
    }

    function applyWidgetSettings(page = pageKey()) {
        if (!page) return;
        const widgets = collectWidgets(page);
        const settings = ensureDefaults(page, widgets);
        const ordered = widgets.slice().sort((a, b) => (settings[a.id]?.order || 0) - (settings[b.id]?.order || 0));
        ordered.forEach(widget => applySettingsToWidget(widget, settings[widget.id]));
        writeSettings(page, settings);
    }

    async function openWidgetSettings(page = pageKey()) {
        if (!page) return;
        await loadServerSettings(page);
        const widgets = collectWidgets(page);
        const settings = ensureDefaults(page, widgets);
        const rows = widgets
            .sort((a, b) => (settings[a.id].order || 0) - (settings[b.id].order || 0))
            .map((widget) => {
                const setting = settings[widget.id];
                const chartOptions = [''].concat(CHART_TYPES).map(type => `<option value="${type}" ${setting.chartType === type ? 'selected' : ''}>${type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Default'}</option>`).join('');
                const lockedDataSource = APPROVED_SOURCES.includes(setting.dataSource) ? setting.dataSource : widget.source;
                const sourceOptions = APPROVED_SOURCES.map(source => `<option value="${source}" ${lockedDataSource === source ? 'selected' : ''}>${source}</option>`).join('');
                const isStat = widget.type === 'stat';
                const isChart = widget.type === 'chart';
                const supportsExport = page === 'reports';
                return `<div class="widget-settings-row" data-widget-id="${widget.id}">
                    <div class="widget-row-head">
                        <div>
                            <div class="widget-row-kicker">${widget.type === 'chart' ? 'Chart Widget' : widget.type === 'section' ? 'Report Section' : 'Stat Widget'}</div>
                            <h6>${escapeHtml(setting.title || widget.title)}</h6>
                        </div>
                        <div class="widget-row-switches">
                            <label class="widget-check"><input type="checkbox" class="widget-visible" ${setting.visible !== false ? 'checked' : ''}> Show</label>
                            ${supportsExport ? `<label class="widget-check"><input type="checkbox" class="widget-export-visible" ${setting.exportVisible !== false ? 'checked' : ''}> Export</label>` : ''}
                        </div>
                    </div>
                    <div class="widget-field widget-field-order">
                        <label>Order</label>
                        <input type="number" class="form-control form-control-sm widget-order" value="${escapeHtml(setting.order)}" min="1">
                    </div>
                    <div class="widget-field widget-field-title">
                        <label>Title</label>
                        <input type="text" class="form-control form-control-sm widget-title" value="${escapeHtml(setting.title)}">
                    </div>
                    <div class="widget-field widget-field-wide">
                        <label>${isStat ? 'Metric Text' : 'Subtitle'}</label>
                        <input type="text" class="form-control form-control-sm widget-subtitle" value="${escapeHtml(setting.subtitle)}" ${isStat ? 'readonly aria-readonly="true"' : ''}>
                    </div>
                    <div class="widget-field widget-field-icon">
                        <label>Icon</label>
                        <input type="text" class="form-control form-control-sm widget-icon" value="${escapeHtml(setting.icon)}">
                    </div>
                    <div class="widget-field widget-field-accent">
                        <label>Accent</label>
                        <input type="color" class="form-control form-control-sm form-control-color widget-accent" value="${escapeHtml(setting.accent || '#8b1f2b')}">
                    </div>
                    <div class="widget-field widget-field-size">
                        <label>Size</label>
                        <select class="form-select form-select-sm widget-size">
                            <option value="small" ${setting.size === 'small' ? 'selected' : ''}>Small</option>
                            <option value="medium" ${setting.size === 'medium' ? 'selected' : ''}>Medium</option>
                            <option value="wide" ${setting.size === 'wide' ? 'selected' : ''}>Wide</option>
                            <option value="full" ${setting.size === 'full' ? 'selected' : ''}>Full width</option>
                        </select>
                    </div>
                    <div class="widget-field widget-field-chart">
                        <label>Chart</label>
                        ${isChart
                            ? `<select class="form-select form-select-sm widget-chart-type">${chartOptions}</select>`
                            : `<div class="widget-locked-value">Not a chart</div><input type="hidden" class="widget-chart-type" value="">`}
                    </div>
                    <div class="widget-field widget-field-source">
                        <label>Data Source</label>
                        ${isChart
                            ? `<select class="form-select form-select-sm widget-source">${sourceOptions}</select>`
                            : `<div class="widget-locked-value">${escapeHtml(lockedDataSource)}</div><input type="hidden" class="widget-source" value="${escapeHtml(lockedDataSource)}">`}
                    </div>
                </div>`;
            }).join('');

        ensureModal();
        const modal = document.getElementById('widgetSettingsModal');
        modal.dataset.page = page;
        modal.querySelector('.widget-settings-page').textContent = page === 'dashboard' ? 'Dashboard' : 'Reports';
        modal.querySelector('.widget-settings-list').innerHTML = rows || '<div class="text-center text-muted py-4">No configurable widgets found.</div>';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function saveFromModal() {
        const page = document.getElementById('widgetSettingsModal')?.dataset.page || pageKey();
        if (!page) return;
        const settings = readSettings(page);
        document.querySelectorAll('#widgetSettingsModal .widget-settings-row[data-widget-id]').forEach((row) => {
            const id = row.dataset.widgetId;
            settings[id] = {
                ...(settings[id] || {}),
                visible: row.querySelector('.widget-visible').checked,
                order: parseInt(row.querySelector('.widget-order').value, 10) || 1,
                title: row.querySelector('.widget-title').value.trim(),
                subtitle: row.querySelector('.widget-subtitle').value.trim(),
                icon: row.querySelector('.widget-icon').value.trim(),
                accent: row.querySelector('.widget-accent').value || '#8b1f2b',
                size: row.querySelector('.widget-size').value,
                chartType: row.querySelector('.widget-chart-type').value,
                dataSource: APPROVED_SOURCES.includes(row.querySelector('.widget-source').value) ? row.querySelector('.widget-source').value : 'Bookings',
                exportVisible: row.querySelector('.widget-export-visible') ? row.querySelector('.widget-export-visible').checked : true
            };
        });
        writeSettings(page, settings);
        saveServerSettings(page, settings);
        applyWidgetSettings(page);
        bootstrap.Modal.getInstance(document.getElementById('widgetSettingsModal'))?.hide();
        notifyWidgetSettingsSaved(page);
    }

    function ensureModal() {
        if (document.getElementById('widgetSettingsModal')) return;
        document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="widgetSettingsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable widget-settings-dialog">
                <div class="modal-content widget-settings-modal">
                    <div class="modal-header widget-settings-modal-header">
                        <div>
                            <h5 class="modal-title mb-1"><i class="fas fa-sliders me-2"></i><span class="widget-settings-page">Widget</span> Settings</h5>
                            <p class="mb-0">Control visible widgets, order, labels, chart type, and approved data source.</p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body widget-settings-modal-body">
                        <div class="widget-settings-list"></div>
                    </div>
                    <div class="modal-footer widget-settings-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveWidgetSettingsBtn"><i class="fas fa-save me-1"></i>Save Settings</button>
                    </div>
                </div>
            </div>
        </div>`);
        document.getElementById('saveWidgetSettingsBtn').addEventListener('click', saveFromModal);
    }

    window.openWidgetSettings = openWidgetSettings;
    window.applyWidgetSettings = applyWidgetSettings;

    document.addEventListener('DOMContentLoaded', () => setTimeout(() => {
        const page = pageKey();
        if (!page) return;
        applyWidgetSettings(page);
        loadServerSettings(page).then(() => applyWidgetSettings(page));
    }, 250));
})();
