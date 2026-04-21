/**
 * Activity Logs JavaScript
 * Handles log filtering, pagination, and charts via AJAX
 */

// Store chart instance globally
window.logsCharts = window.logsCharts || {
    timelineChart: null
};

// Extend initPageScripts for logs page (don't overwrite dashboard/analytics)
// Only do this if we haven't already set it up (prevent redeclaration errors)
if (!window.logsInitPageScriptsSetup) {
    window.logsInitPageScriptsSetup = true;
    
    const originalInitPageScripts = window.initPageScripts;

    window.initPageScripts = function() {
        // Check if we're on the logs page
        if ($('#logsFilterCard').length) {
            console.log('Logs page initialized');
            
            // Initialize activity timeline chart
            initActivityTimelineChart();
            
            // Set up pagination click handlers if pagination exists
            $(document).on('click', '#logsPaginationContainer .page-link', function(e) {
                e.preventDefault();
                const $this = $(this);
                const page = parseInt($this.data('page'));
                
                if ($this.parent().hasClass('disabled') || $this.parent().hasClass('active')) {
                    return;
                }
                
                if (page && page > 0) {
                    if (typeof window.loadLogsWithFilter === 'function') {
                        window.loadLogsWithFilter(page);
                    }
                }
            });
            return; // Don't continue to other page scripts
        }
        
        // If not logs page, call original initPageScripts (for dashboard/analytics)
        if (originalInitPageScripts && typeof originalInitPageScripts === 'function') {
            originalInitPageScripts();
        }
    };
}

/**
 * Initialize Activity Timeline Chart
 */
function initActivityTimelineChart() {
    const ctx = document.getElementById('activityTimelineChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (window.logsCharts.timelineChart) {
        window.logsCharts.timelineChart.destroy();
    }
    
    const timelineData = window.LOGS_TIMELINE_DATA || [];
    
    // Prepare data for chart
    const labels = timelineData.map(item => {
        const date = new Date(item.date + 'T00:00:00');
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    const data = timelineData.map(item => item.count);
    
    // Use shared chart palette for consistent theme across pages
    const chartPalette = (typeof window.getTapparkChartPalette === 'function')
        ? window.getTapparkChartPalette()
        : null;
    const isDark = $('html').attr('data-bs-theme') === 'dark';
    const gridColor = chartPalette?.grid || (isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)');
    const textColor = chartPalette?.tickColor || (isDark ? 'rgba(255, 255, 255, 0.7)' : 'rgba(0, 0, 0, 0.7)');
    const lineColor = chartPalette?.series?.[1] || chartPalette?.maroon || '#800000';
    const fillColor = chartPalette?.seriesSoft?.[1] || chartPalette?.maroonLight || 'rgba(128, 0, 0, 0.1)';
    const pointBorderColor = chartPalette?.borderColor || '#ffffff';
    const tooltipBg = chartPalette?.tooltipBg || (isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(255, 255, 255, 0.95)');
    const tooltipText = chartPalette?.tooltipText || textColor;
    const tooltipBorder = chartPalette?.tooltipBorder || lineColor;
    
    window.logsCharts.timelineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Activities',
                data: data,
                borderColor: lineColor,
                backgroundColor: fillColor,
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: lineColor,
                pointBorderColor: pointBorderColor,
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: tooltipBg,
                    titleColor: tooltipText,
                    bodyColor: tooltipText,
                    borderColor: tooltipBorder,
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: textColor,
                        stepSize: 1
                    },
                    grid: {
                        color: gridColor
                    }
                },
                x: {
                    ticks: {
                        color: textColor,
                        maxRotation: 30,
                        minRotation: 20,
                        autoSkip: true,
                        maxTicksLimit: 14
                    },
                    grid: {
                        color: gridColor,
                        display: false
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
}

// Utility function to format numbers
function formatNumber(num) {
    if (typeof num !== 'number') return '0';
    return num.toLocaleString();
}

