<div class="container-fluid">
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="mb-2 fw-bold">
                        <i class="fas fa-star me-3 text-primary"></i>Feedback
                    </h2>
                    <p class="mb-0 text-secondary">View user feedback and reply as admin</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Feedback -->
        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card-modern h-100 border-0 shadow-sm hover-lift stats-card-maroon">
                <div class="card-body text-white position-relative overflow-hidden p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-2 fw-semibold" style="font-size: 0.95rem; opacity: 0.9;">Total Feedback</h5>
                            <h2 class="mb-0 fw-bold text-white stat-value" id="stat-total" style="font-size: 2.5rem; line-height: 1.2;">
                                <?= number_format((int)($stats['total'] ?? 0)) ?>
                            </h2>
                        </div>
                        <div class="stats-icon" style="font-size: 2.5rem; opacity: 0.2;">
                            <i class="fas fa-comments"></i>
                        </div>
                    </div>
                    <p class="mb-0 text-white" style="opacity: 0.8; font-size: 0.9rem;">
                        <i class="fas fa-clock me-2"></i>All time
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Average Rating -->
        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card-modern h-100 border-0 shadow-sm hover-lift stats-card-maroon">
                <div class="card-body text-white position-relative overflow-hidden p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-2 fw-semibold" style="font-size: 0.95rem; opacity: 0.9;">Average Rating</h5>
                            <h2 class="mb-0 fw-bold text-white stat-value" id="stat-average" style="font-size: 2.5rem; line-height: 1.2;">
                                <?= number_format((float)($stats['average'] ?? 0), 1) ?>
                            </h2>
                        </div>
                        <div class="stats-icon" style="font-size: 2.5rem; opacity: 0.2;">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="mb-0 text-white" style="opacity: 0.8; font-size: 0.9rem;">
                        <i class="fas fa-star-half-alt me-2"></i>Out of 5.0
                    </p>
                </div>
            </div>
        </div>

        <!-- Positive Feedback -->
        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card-modern h-100 border-0 shadow-sm hover-lift stats-card-maroon">
                <div class="card-body text-white position-relative overflow-hidden p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-2 fw-semibold" style="font-size: 0.95rem; opacity: 0.9;">Positive</h5>
                            <h2 class="mb-0 fw-bold text-white stat-value" id="stat-positive" style="font-size: 2.5rem; line-height: 1.2;">
                                <?= number_format((int)($stats['positive'] ?? 0)) ?>
                            </h2>
                        </div>
                        <div class="stats-icon" style="font-size: 2.5rem; opacity: 0.2;">
                            <i class="fas fa-thumbs-up"></i>
                        </div>
                    </div>
                    <p class="mb-0 text-white" style="opacity: 0.8; font-size: 0.9rem;">
                        <i class="fas fa-check-circle me-2"></i>4-5 Stars
                    </p>
                </div>
            </div>
        </div>

        <!-- Critical Issues -->
        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card-modern h-100 border-0 shadow-sm hover-lift stats-card-maroon">
                <div class="card-body text-white position-relative overflow-hidden p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-2 fw-semibold" style="font-size: 0.95rem; opacity: 0.9;">Critical Issues</h5>
                            <h2 class="mb-0 fw-bold text-white stat-value" id="stat-critical" style="font-size: 2.5rem; line-height: 1.2;">
                                <?= number_format((int)($stats['critical'] ?? 0)) ?>
                            </h2>
                        </div>
                        <div class="stats-icon" style="font-size: 2.5rem; opacity: 0.2;">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                    <p class="mb-0 text-white" style="opacity: 0.8; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i>1-3 Stars
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4" id="feedbackFiltersCard">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="d-flex flex-wrap gap-3 align-items-end">
                <!-- Data storage for logic -->
                <input type="hidden" id="filterChanged" value="0">

                <div class="flex-grow-1" style="min-width: 200px;">
                    <label class="form-label"><i class="fas fa-filter me-2"></i>Rating</label>
                    <?php
                        $selectedRating = (string)($rating ?? '');
                        $ratingOptions = [
                            '' => 'All Ratings',
                            '5' => '5 Stars',
                            '4' => '4 Stars',
                            '3' => '3 Stars',
                            '2' => '2 Stars',
                            '1' => '1 Star',
                        ];
                    ?>
                    <input type="hidden" id="feedbackRatingFilter" value="<?= esc($selectedRating) ?>">
                    <div class="tappark-select" id="feedbackRatingSelect">
                        <button type="button"
                            class="tappark-select-toggle"
                            id="feedbackRatingSelectToggle"
                            aria-haspopup="listbox"
                            aria-expanded="false">
                            <span id="feedbackRatingSelectLabel"><?= esc($ratingOptions[$selectedRating] ?? 'All Ratings') ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="tappark-select-menu" role="listbox" aria-labelledby="feedbackRatingSelectToggle">
                            <?php foreach ($ratingOptions as $value => $label): ?>
                                <button type="button"
                                    class="tappark-select-option<?= $selectedRating === (string)$value ? ' active' : '' ?>"
                                    role="option"
                                    data-value="<?= esc($value) ?>"
                                    data-label="<?= esc($label) ?>"
                                    aria-selected="<?= $selectedRating === (string)$value ? 'true' : 'false' ?>">
                                    <?= esc($label) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="flex-grow-1" style="min-width: 150px;">
                    <label class="form-label"><i class="fas fa-calendar-alt me-2"></i>Date From</label>
                    <input type="hidden" id="feedbackDateFrom" value="<?= esc((string)($date_from ?? '')) ?>">
                    <div class="tappark-date" data-date-target="feedbackDateFrom">
                        <button type="button" class="tappark-date-toggle" aria-haspopup="dialog" aria-expanded="false">
                            <span class="tappark-date-label">mm/dd/yyyy</span>
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <div class="tappark-date-menu" role="dialog" aria-label="Date From calendar"></div>
                    </div>
                </div>
                <div class="flex-grow-1" style="min-width: 150px;">
                    <label class="form-label"><i class="fas fa-calendar-alt me-2"></i>Date To</label>
                    <input type="hidden" id="feedbackDateTo" value="<?= esc((string)($date_to ?? '')) ?>">
                    <div class="tappark-date" data-date-target="feedbackDateTo">
                        <button type="button" class="tappark-date-toggle" aria-haspopup="dialog" aria-expanded="false">
                            <span class="tappark-date-label">mm/dd/yyyy</span>
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                        <div class="tappark-date-menu" role="dialog" aria-label="Date To calendar"></div>
                    </div>
                </div>
                
                <!-- Action Buttons: Apply & Clear -->
                <div class="d-flex gap-2" id="feedbackFilterActions">
                    <button type="button" class="btn btn-primary" id="applyFeedbackFiltersBtn">
                        <i class="fas fa-filter me-2"></i>Apply
                    </button>
                    <button type="button" class="btn btn-secondary" id="clearFeedbackFiltersBtn">
                        <i class="fas fa-times me-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="feedbackContent" class="position-relative" style="min-height: 200px;">
        <!-- Loading Spinner Overlay -->
        <div id="feedbackLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center" style="background: rgba(255,255,255,0.7); z-index: 10; border-radius: 8px;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div id="feedbackListContainer">
            <?= view('pages/feedback/content', [
            'feedbacks' => $feedbacks ?? [],
            'pagination' => $pagination ?? null,
            'rating' => $rating ?? null,
            'status' => $status ?? null,
            'date_from' => $date_from ?? null,
            'date_to' => $date_to ?? null,
            'per_page' => $per_page ?? null,
            'sort_by' => $sort_by ?? null,
            'sort_dir' => $sort_dir ?? null
        ]) ?>
        </div>
    </div>
</div>

<script>
(function() {
    const $rating = $('#feedbackRatingFilter');
    const $dateFrom = $('#feedbackDateFrom');
    const $dateTo = $('#feedbackDateTo');
    const $clear = $('#clearFeedbackFiltersBtn');
    const $ratingSelect = $('#feedbackRatingSelect');
    const $ratingToggle = $('#feedbackRatingSelectToggle');
    const $ratingLabel = $('#feedbackRatingSelectLabel');
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const shortDays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    function getBaseUrl() {
        return (typeof window.APP_BASE_URL !== 'undefined' && window.APP_BASE_URL) ? window.APP_BASE_URL : (typeof window.BASE_URL !== 'undefined' ? window.BASE_URL : '');
    }

    function getCurrentParams() {
        return {
            rating: $rating.val() || '',
            date_from: $dateFrom.val() || '',
            date_to: $dateTo.val() || '',
            per_page: $('#feedbackPerPageSelect').val() || '',
            page: $('#feedbackCurrentPage').val() || '1',
            sort_by: $('#feedbackCurrentSortBy').val() || '',
            sort_dir: $('#feedbackCurrentSortDir').val() || ''
        };
    }

    function buildQuery(params) {
        const qs = new URLSearchParams();
        Object.keys(params || {}).forEach(k => {
            const v = params[k];
            if (v !== null && v !== undefined && String(v) !== '' && k !== 'list_only') {
                qs.set(k, v);
            }
        });
        return qs.toString();
    }

    function fetchStats() {
        $.ajax({
            url: getBaseUrl() + 'feedback/stats',
            method: 'GET',
            success: function(stats) {
                if (stats) {
                    $('#stat-total').text(Number(stats.total).toLocaleString());
                    $('#stat-average').text(Number(stats.average || 0).toFixed(1));
                    $('#stat-positive').text(Number(stats.positive).toLocaleString());
                    $('#stat-critical').text(Number(stats.critical).toLocaleString());
                }
            },
            error: function() {
                $('.stat-value').text('0');
            }
        });
    }

    function updateList(partialParams, options) {
        options = options || {};
        const params = Object.assign({}, getCurrentParams(), partialParams || {});
        params.list_only = '1';
        const url = getBaseUrl() + 'feedback?' + buildQuery(params) + '&list_only=1';

        if (!options.silent) {
            $('#feedbackLoadingOverlay').removeClass('d-none').addClass('d-flex');
        }

        return $.ajax({
            url: url,
            method: 'GET',
            success: function(html) {
                $('#feedbackLoadingOverlay').removeClass('d-flex').addClass('d-none');
                $('#feedbackListContainer').html(html);
            },
            error: function() {
                $('#feedbackLoadingOverlay').removeClass('d-flex').addClass('d-none');
                if (typeof window.loadPage === 'function') {
                    const fullUrl = 'feedback?' + buildQuery(params);
                    window.loadPage(fullUrl, 'Feedback');
                } else {
                    window.location.href = 'feedback?' + buildQuery(params);
                }
            }
        });
    }

    function buildUrl(rating) {
        const params = new URLSearchParams();
        if (rating) {
            params.set('rating', rating);
        }
        const qs = params.toString();
        return qs ? ('feedback?' + qs) : 'feedback';
    }

    // Listen for global records per page updates
    document.addEventListener('app-records-per-page-updated', function (e) {
        const newPerPage = e.detail.perPage;
        console.log('Feedback page: Records per page updated to', newPerPage);
        
        // Sync the per-page select if it exists
        $('#feedbackPerPageSelect').val(newPerPage);
        
        // Update list with new per_page and reset to page 1
        updateList({
            per_page: newPerPage,
            page: '1'
        });
    });

    // --- UI Logic for Filters ---
    function closeRatingSelect() {
        $ratingSelect.removeClass('open');
        $ratingToggle.attr('aria-expanded', 'false');
    }

    function setRatingSelect(value, label) {
        $rating.val(value || '');
        $ratingLabel.text(label || 'All Ratings');
        $ratingSelect.find('.tappark-select-option').each(function() {
            const isActive = String($(this).data('value') || '') === String(value || '');
            $(this).toggleClass('active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });
    }

    $ratingToggle.off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const willOpen = !$ratingSelect.hasClass('open');
        $('.tappark-select.open').removeClass('open').find('.tappark-select-toggle').attr('aria-expanded', 'false');
        $ratingSelect.toggleClass('open', willOpen);
        $ratingToggle.attr('aria-expanded', willOpen ? 'true' : 'false');
    });

    $ratingSelect.find('.tappark-select-option').off('click').on('click', function() {
        setRatingSelect($(this).data('value') || '', $(this).data('label') || 'All Ratings');
        closeRatingSelect();
    });

    $(document).off('click.feedbackRatingSelect').on('click.feedbackRatingSelect', function(e) {
        if (!$(e.target).closest('#feedbackRatingSelect').length) {
            closeRatingSelect();
        }
    });

    $ratingToggle.off('keydown').on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeRatingSelect();
        }
    });

    function parseIsoDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) return null;
        return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    }

    function toIsoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function toDisplayDate(value) {
        const date = parseIsoDate(value);
        if (!date) return 'mm/dd/yyyy';
        return `${String(date.getMonth() + 1).padStart(2, '0')}/${String(date.getDate()).padStart(2, '0')}/${date.getFullYear()}`;
    }

    function closeDatePickers() {
        $('.tappark-date.open').removeClass('open').find('.tappark-date-toggle').attr('aria-expanded', 'false');
    }

    function renderDatePicker($picker) {
        const targetId = $picker.data('date-target');
        const $input = $('#' + targetId);
        const selected = parseIsoDate($input.val());
        let viewDate = $picker.data('viewDate');
        if (!(viewDate instanceof Date)) {
            viewDate = selected ? new Date(selected) : new Date();
        }

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const previousMonthDays = new Date(year, month, 0).getDate();
        const todayIso = toIsoDate(new Date());
        const selectedIso = selected ? toIsoDate(selected) : '';
        const cells = [];

        for (let i = firstDay - 1; i >= 0; i--) {
            cells.push({ day: previousMonthDays - i, muted: true, date: new Date(year, month - 1, previousMonthDays - i) });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            cells.push({ day, muted: false, date: new Date(year, month, day) });
        }

        while (cells.length % 7 !== 0) {
            const nextDay = cells.length - firstDay - daysInMonth + 1;
            cells.push({ day: nextDay, muted: true, date: new Date(year, month + 1, nextDay) });
        }

        const dayHeaders = shortDays.map(day => `<span class="tappark-date-weekday">${day}</span>`).join('');
        const dayButtons = cells.map(cell => {
            const iso = toIsoDate(cell.date);
            const classes = [
                'tappark-date-day',
                cell.muted ? 'muted' : '',
                iso === selectedIso ? 'selected' : '',
                iso === todayIso ? 'today' : ''
            ].filter(Boolean).join(' ');
            return `<button type="button" class="${classes}" data-date="${iso}">${cell.day}</button>`;
        }).join('');

        $picker.find('.tappark-date-label').text(toDisplayDate($input.val()));
        $picker.find('.tappark-date-menu').html(`
            <div class="tappark-date-header">
                <button type="button" class="tappark-date-nav" data-date-nav="-1" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
                <div class="tappark-date-title">${monthNames[month]} ${year}</div>
                <button type="button" class="tappark-date-nav" data-date-nav="1" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="tappark-date-grid">${dayHeaders}${dayButtons}</div>
            <div class="tappark-date-footer">
                <button type="button" class="tappark-date-action" data-date-clear>Clear</button>
                <button type="button" class="tappark-date-action" data-date-today>Today</button>
            </div>
        `);
        $picker.data('viewDate', viewDate);
    }

    $('.tappark-date').each(function() {
        renderDatePicker($(this));
    });

    function refreshDatePickers() {
        $('.tappark-date').each(function() {
            renderDatePicker($(this));
        });
    }

    $(document).off('click.tapparkDateToggle').on('click.tapparkDateToggle', '.tappark-date-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $picker = $(this).closest('.tappark-date');
        const willOpen = !$picker.hasClass('open');
        closeDatePickers();
        closeRatingSelect();
        if (willOpen) {
            renderDatePicker($picker);
            $picker.addClass('open');
            $(this).attr('aria-expanded', 'true');
        }
    });

    $(document).off('click.tapparkDateDay').on('click.tapparkDateDay', '.tappark-date-day', function() {
        const $picker = $(this).closest('.tappark-date');
        $('#' + $picker.data('date-target')).val($(this).data('date'));
        $picker.data('viewDate', parseIsoDate($(this).data('date')));
        renderDatePicker($picker);
        closeDatePickers();
    });

    $(document).off('click.tapparkDateNav').on('click.tapparkDateNav', '.tappark-date-nav', function() {
        const $picker = $(this).closest('.tappark-date');
        const viewDate = $picker.data('viewDate') instanceof Date ? $picker.data('viewDate') : new Date();
        $picker.data('viewDate', new Date(viewDate.getFullYear(), viewDate.getMonth() + Number($(this).data('date-nav')), 1));
        renderDatePicker($picker);
    });

    $(document).off('click.tapparkDateClear').on('click.tapparkDateClear', '[data-date-clear]', function() {
        const $picker = $(this).closest('.tappark-date');
        $('#' + $picker.data('date-target')).val('');
        renderDatePicker($picker);
        closeDatePickers();
    });

    $(document).off('click.tapparkDateToday').on('click.tapparkDateToday', '[data-date-today]', function() {
        const $picker = $(this).closest('.tappark-date');
        const today = new Date();
        $('#' + $picker.data('date-target')).val(toIsoDate(today));
        $picker.data('viewDate', today);
        renderDatePicker($picker);
        closeDatePickers();
    });

    $(document).off('click.tapparkDateOutside').on('click.tapparkDateOutside', function(e) {
        if (!$(e.target).closest('.tappark-date').length) {
            closeDatePickers();
        }
    });
    
    // Apply Button Click
    $('#applyFeedbackFiltersBtn').off('click').on('click', function() {
        updateList({
            rating: $rating.val() || '',
            date_from: $dateFrom.val() || '',
            date_to: $dateTo.val() || '',
            page: '1'
        });
        // Optional: Hide actions after apply? users usually keep them visible or disable them
        // For consistency with Users page (if that was the logic), we usually just load
    });

    // Clear Button Click
    $clear.off('click').on('click', function() {
        setRatingSelect('', 'All Ratings');
        $dateFrom.val('');
        $dateTo.val('');
        refreshDatePickers();
        
        // Hide actions since we reset
        // Or trigger update immediately for Clear
        updateList({
            rating: '',
            date_from: '',
            date_to: '',
            page: '1',
            sort_by: '',
            sort_dir: ''
        });
    });

    $(document).off('change', '#feedbackPerPageSelect').on('change', '#feedbackPerPageSelect', function() {
        updateList({
            per_page: $(this).val(),
            page: '1'
        });
    });

    $(document).off('click', '.feedback-sortable').on('click', '.feedback-sortable', function(e) {
        e.preventDefault();
        const sortBy = $(this).data('sort-by');
        if (!sortBy) return;
        const currentBy = $('#feedbackCurrentSortBy').val() || '';
        const currentDir = ($('#feedbackCurrentSortDir').val() || 'desc').toLowerCase();
        let nextDir = 'asc';
        if (currentBy === sortBy) {
            nextDir = currentDir === 'asc' ? 'desc' : 'asc';
        }
        updateList({
            sort_by: sortBy,
            sort_dir: nextDir,
            page: '1'
        });
    });

    $(document).off('click', '#feedbackPagination a.page-link').on('click', '#feedbackPagination a.page-link', function(e) {
        e.preventDefault();
        if ($(this).parent().hasClass('disabled') || $(this).parent().hasClass('active')) {
            return;
        }
        const page = $(this).data('page');
        if (!page) return;
        updateList({
            page: String(page)
        });
    });

    $(document).off('click', '#exportFeedbackBtn').on('click', '#exportFeedbackBtn', function() {
        const params = getCurrentParams();
        const exportUrl = getBaseUrl() + 'feedback/export?' + buildQuery(params);
        window.location.href = exportUrl;
    });

    // Initial Load
    fetchStats();
    window.refreshCurrentPage = function(options) {
        options = options || {};
        fetchStats();
        return updateList({
            page: $('#feedbackCurrentPage').val() || '1'
        }, options);
    };
})();
</script>
