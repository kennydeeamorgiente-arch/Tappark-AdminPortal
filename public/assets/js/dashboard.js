// dashboard.js
// Dashboard-specific JavaScript with Chart.js integration

// Chart instances stored globally to allow destruction
window.dashboardCharts = window.dashboardCharts || {
    revenueChart: null,
    occupancyChart: null,
    bookingsChart: null,
    userGrowthChart: null,
    hourBalanceChart: null,
    avgRatingChart: null,
    guestBookingsTrendChart: null
};

// Register global chart theme helpers once
if (!window.__tapparkChartThemeHelpersInitialized) {
    window.__tapparkChartThemeHelpersInitialized = true;

    const computeChartThemeTokens = () => {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            isDarkMode,
            textColor: isDarkMode ? '#f4dde1' : '#44242a',
            gridColor: isDarkMode ? 'rgba(241, 197, 205, 0.18)' : 'rgba(139, 31, 43, 0.12)',
            tooltipBg: isDarkMode ? 'rgba(44, 30, 35, 0.94)' : 'rgba(255, 248, 249, 0.96)',
            tooltipBorder: isDarkMode ? 'rgba(241, 177, 189, 0.32)' : 'rgba(139, 31, 43, 0.22)'
        };
    };

    window.applyChartThemeDefaults = function() {
        const tokens = computeChartThemeTokens();
        window.currentChartThemeTokens = tokens;

        if (typeof Chart === 'undefined') {
            return tokens;
        }

        Chart.defaults.color = tokens.textColor;
        Chart.defaults.borderColor = tokens.gridColor;
        Chart.defaults.font.family = '\"Poppins\", "Segoe UI", "Inter", sans-serif';

        Chart.defaults.plugins = Chart.defaults.plugins || {};
        Chart.defaults.plugins.legend = Chart.defaults.plugins.legend || {};
        Chart.defaults.plugins.legend.labels = Chart.defaults.plugins.legend.labels || {};
        Chart.defaults.plugins.legend.labels.color = tokens.textColor;

        Chart.defaults.plugins.tooltip = Chart.defaults.plugins.tooltip || {};
        Chart.defaults.plugins.tooltip.backgroundColor = tokens.tooltipBg;
        Chart.defaults.plugins.tooltip.borderColor = tokens.tooltipBorder;
        Chart.defaults.plugins.tooltip.titleColor = tokens.textColor;
        Chart.defaults.plugins.tooltip.bodyColor = tokens.textColor;

        Chart.defaults.scale = Chart.defaults.scale || {};
        Chart.defaults.scale.grid = Chart.defaults.scale.grid || {};
        Chart.defaults.scale.grid.color = tokens.gridColor;
        Chart.defaults.scale.ticks = Chart.defaults.scale.ticks || {};
        Chart.defaults.scale.ticks.color = tokens.textColor;

        return tokens;
    };

    window.refreshExistingChartsTheme = function() {
        const tokens = window.applyChartThemeDefaults ? window.applyChartThemeDefaults() : computeChartThemeTokens();
        const palette = (typeof window.getTapparkChartPalette === 'function')
            ? window.getTapparkChartPalette()
            : null;
        const applyTokensToChart = (chart) => {
            if (!chart || !chart.options) return;

            if (chart.options.scales) {
                Object.values(chart.options.scales).forEach(scale => {
                    if (!scale) return;
                    if (scale.ticks) {
                        scale.ticks.color = tokens.textColor;
                    }
                    if (scale.grid) {
                        scale.grid.color = tokens.gridColor;
                    }
                });
            }

            if (chart.options.plugins?.legend?.labels) {
                chart.options.plugins.legend.labels.color = tokens.textColor;
            }

            if (chart.options.plugins?.tooltip) {
                const tooltip = chart.options.plugins.tooltip;
                tooltip.backgroundColor = tokens.tooltipBg;
                tooltip.borderColor = tokens.tooltipBorder;
                tooltip.titleColor = tokens.textColor;
                tooltip.bodyColor = tokens.textColor;
            }

            // Keep logs activity timeline colors synced when switching light/dark mode
            if (chart.canvas?.id === 'activityTimelineChart' && Array.isArray(chart.data?.datasets)) {
                const lineColor = palette?.series?.[1] || palette?.maroon || (tokens.isDarkMode ? '#b9959c' : '#8b1f2b');
                const fillColor = palette?.seriesSoft?.[1] || palette?.maroonLight || (tokens.isDarkMode ? 'rgba(185, 149, 156, 0.24)' : 'rgba(139, 31, 43, 0.16)');
                const pointBorderColor = palette?.borderColor || (tokens.isDarkMode ? '#ffeef2' : '#ffffff');

                chart.data.datasets.forEach(dataset => {
                    if (!dataset) return;
                    dataset.borderColor = lineColor;
                    dataset.backgroundColor = fillColor;
                    dataset.pointBackgroundColor = lineColor;
                    dataset.pointBorderColor = pointBorderColor;
                });
            }

            chart.update('none');
        };

        const dashboards = window.dashboardCharts ? Object.values(window.dashboardCharts) : [];
        const reports = window.reportsCharts ? Object.values(window.reportsCharts) : [];
        const logs = window.logsCharts ? Object.values(window.logsCharts) : [];
        [...dashboards, ...reports, ...logs].forEach(applyTokensToChart);
    };
}

function buildTapparkChartPalette(isDarkMode) {
    if (isDarkMode) {
        return {
            maroon: '#d27a84',
            maroonLight: 'rgba(210, 122, 132, 0.22)',
            maroonGradient: ['#bf646f', '#d27a84'],
            maroonFillTop: 'rgba(210, 122, 132, 0.5)',
            maroonFillBottom: 'rgba(210, 122, 132, 0.08)',
            maroonBarTop: 'rgba(210, 122, 132, 0.84)',
            maroonBarBottom: 'rgba(191, 100, 111, 0.44)',
            accent: '#b9959c',
            accentLight: 'rgba(185, 149, 156, 0.24)',
            accentFillTop: 'rgba(185, 149, 156, 0.5)',
            accentFillBottom: 'rgba(185, 149, 156, 0.08)',
            accentBarTop: 'rgba(185, 149, 156, 0.8)',
            accentBarBottom: 'rgba(155, 123, 130, 0.42)',
            amber: '#c46d7a',
            amberLight: 'rgba(196, 109, 122, 0.24)',
            gray: '#c3b2b6',
            grayLight: 'rgba(195, 178, 182, 0.2)',
            grayGradient: ['#ab9ca0', '#d1c6c9'],
            grayFillTop: 'rgba(195, 178, 182, 0.46)',
            grayFillBottom: 'rgba(195, 178, 182, 0.08)',
            series: ['#d27a84', '#b9959c', '#c46d7a', '#9f7f86', '#c3b2b6', '#a58d92'],
            seriesSoft: ['rgba(210, 122, 132, 0.24)', 'rgba(185, 149, 156, 0.24)', 'rgba(196, 109, 122, 0.23)', 'rgba(159, 127, 134, 0.22)', 'rgba(195, 178, 182, 0.22)', 'rgba(165, 141, 146, 0.22)'],
            statusColors: {
                completed: '#d27a84',
                pending: '#b9959c',
                cancelled: '#a0646e',
                other: '#c3b2b6'
            },
            borderColor: 'rgba(255, 233, 238, 0.48)',
            surfaceContrast: '#fff5f7',
            tooltipBg: 'rgba(44, 30, 35, 0.95)',
            tooltipBorder: 'rgba(210, 122, 132, 0.5)',
            tooltipText: '#ffeef2',
            grid: 'rgba(241, 197, 205, 0.18)',
            tickColor: '#f3d8dd',
            emptyColor: 'rgba(195, 178, 182, 0.25)'
        };
    }

    return {
        maroon: '#8b1f2b',
        maroonLight: 'rgba(139, 31, 43, 0.15)',
        maroonGradient: ['#8b1f2b', '#b13b49'],
        maroonFillTop: 'rgba(177, 59, 73, 0.42)',
        maroonFillBottom: 'rgba(177, 59, 73, 0.04)',
        maroonBarTop: 'rgba(177, 59, 73, 0.8)',
        maroonBarBottom: 'rgba(177, 59, 73, 0.3)',
        accent: '#7a555d',
        accentLight: 'rgba(122, 85, 93, 0.16)',
        accentFillTop: 'rgba(122, 85, 93, 0.4)',
        accentFillBottom: 'rgba(122, 85, 93, 0.05)',
        accentBarTop: 'rgba(122, 85, 93, 0.72)',
        accentBarBottom: 'rgba(122, 85, 93, 0.3)',
        amber: '#a35b66',
        amberLight: 'rgba(163, 91, 102, 0.2)',
        gray: '#6f5e63',
        grayLight: 'rgba(111, 94, 99, 0.15)',
        grayGradient: ['#6f5e63', '#8a787d'],
        grayFillTop: 'rgba(111, 94, 99, 0.4)',
        grayFillBottom: 'rgba(111, 94, 99, 0.05)',
        series: ['#8b1f2b', '#7a555d', '#a35b66', '#6f5e63', '#9b7d84', '#5e4f53'],
        seriesSoft: ['rgba(139, 31, 43, 0.18)', 'rgba(122, 85, 93, 0.16)', 'rgba(163, 91, 102, 0.18)', 'rgba(111, 94, 99, 0.15)', 'rgba(155, 125, 132, 0.15)', 'rgba(94, 79, 83, 0.15)'],
        statusColors: {
            completed: '#8b1f2b',
            pending: '#a35b66',
            cancelled: '#6b1c25',
            other: '#6f5e63'
        },
        borderColor: 'rgba(255, 255, 255, 0.92)',
        surfaceContrast: '#ffffff',
        tooltipBg: 'rgba(255, 248, 249, 0.97)',
        tooltipBorder: 'rgba(139, 31, 43, 0.26)',
        tooltipText: '#402229',
        grid: 'rgba(139, 31, 43, 0.1)',
        tickColor: '#65454c',
        emptyColor: 'rgba(200, 200, 200, 0.3)'
    };
}

if (typeof window.getTapparkChartPalette !== 'function') {
    window.getTapparkChartPalette = function() {
        const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return buildTapparkChartPalette(isDarkMode);
    };
}

// Utility function to get chart colors based on theme
function getChartColors() {
    return window.getTapparkChartPalette();
}

function createVerticalGradient(chart, topColor, bottomColor) {
    if (!chart || !chart.ctx || !chart.chartArea) {
        return bottomColor;
    }
    const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
    gradient.addColorStop(0, topColor);
    gradient.addColorStop(1, bottomColor);
    return gradient;
}

// Function to clear "No Data" messages
function clearNoDataMessages(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas || !canvas.parentNode) return;

    canvas.style.display = 'block';
    var noDataDivs = canvas.parentNode.querySelectorAll('.text-center.text-muted.py-5');
    noDataDivs.forEach(function(div) { div.remove(); });
}

// Function to show "No Data" message
function showNoDataMessage(canvasId, iconClass, titleText, subtitleText) {
    var canvas = document.getElementById(canvasId);
    if (!canvas || !canvas.parentNode) return;

    var existing = canvas.parentNode.querySelectorAll('.text-center.text-muted.py-5');
    existing.forEach(function(div) { div.remove(); });

    canvas.style.display = 'none';
    var noDataDiv = document.createElement('div');
    noDataDiv.className = 'text-center text-muted py-5';
    noDataDiv.innerHTML = '<i class="' + iconClass + ' fa-3x mb-3 opacity-50"></i>' +
        '<h5>' + titleText + '</h5>' +
        '<p class="small">' + subtitleText + '</p>';
    canvas.parentNode.appendChild(noDataDiv);
}

function hasAnyPositiveValue(values) {
    return Array.isArray(values) && values.some(function(v) { return (parseFloat(v) || 0) > 0; });
}

// Modern Growth Animation Configurations
if (typeof window.dashboardAnimations === 'undefined') {
    window.dashboardAnimations = {
        // Line chart growth animation - draws from bottom to top
        lineGrowth: {
            duration: 3000,
            easing: 'easeOutQuart',
            delay: (context) => {
                return context.dataIndex * 200;
            }
        },
        
        // Bar chart growth animation - grows from bottom
        barGrowth: {
            duration: 2500,
            easing: 'easeOutBounce',
            delay: (context) => {
                return context.dataIndex * 150;
            }
        },
        
        // Doughnut chart modern animation
        doughnutModern: {
            duration: 2500,
            easing: 'easeOutElastic',
            delay: (context) => {
                return context.dataIndex * 300;
            }
        }
    };
}

// Function to enhance all chart animations
function enhanceDashboardChartAnimations() {
    Object.keys(window.dashboardCharts).forEach(key => {
        const chart = window.dashboardCharts[key];
        if (chart) {
            // Check if the chart's canvas element still exists in the DOM
            const canvas = chart.canvas;
            if (!canvas || !canvas.parentNode || !document.body.contains(canvas)) {
                // Canvas doesn't exist, destroy the chart
                chart.destroy();
                window.dashboardCharts[key] = null;
                return;
            }
            
            // Apply modern growth animations based on chart type
            if (chart.config.type === 'line') {
                chart.options.animation = window.dashboardAnimations.lineGrowth;
            } else if (chart.config.type === 'bar') {
                chart.options.animation = window.dashboardAnimations.barGrowth;
            } else if (chart.config.type === 'doughnut') {
                chart.options.animation = window.dashboardAnimations.doughnutModern;
            }
            
            // Add hover animations
            chart.options.interaction = {
                intersect: false,
                mode: 'index'
            };
            
            // Update the chart to apply animations (only if canvas is still valid)
            try {
                chart.update('active');
            } catch (error) {
                console.warn(`⚠️ Could not update chart ${key}:`, error);
                // Destroy the chart if update fails
                chart.destroy();
                window.dashboardCharts[key] = null;
            }
        }
    });
}

// Reset dashboard initialization flag (call this when loading fresh dashboard content)
function resetDashboardInitialization() {
    dashboardInitialized = false;
    console.log('📊 Dashboard initialization flag reset');
}

// Note: Filter button setup is now handled by the date_filter component partial
// The component calls loadDashboardWithFilter() when filters change

// Dashboard initialization flag to prevent multiple initializations
let dashboardInitialized = false;

// Dashboard-specific initialization function
function initDashboardCharts() {
    // Prevent multiple initializations
    if (dashboardInitialized) {
        console.log('📊 Dashboard already initialized, skipping...');
        return;
    }
    
    console.log('📊 Dashboard initialized');
    dashboardInitialized = true;
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js is not loaded!');
        return;
    }
    
    console.log('✅ Chart.js version:', Chart.version);
    window.applyChartThemeDefaults?.();
    
    // Check if dashboard data is available
    if (typeof window.dashboardData === 'undefined') {
        console.error('❌ Dashboard data not found! Make sure window.dashboardData is set in the view.');
        console.log('⏳ Retrying in 200ms...');
        // Retry after a short delay (in case data is still being set)
        setTimeout(function() {
            if (typeof window.dashboardData !== 'undefined') {
                dashboardInitialized = false; // Reset flag for retry
                initDashboardCharts();
            } else {
                console.error('❌ Dashboard data still not available after retry');
            }
        }, 200);
        return;
    }
    
    // Check if we're actually on the dashboard page (canvas elements should exist)
    const testCanvas = document.getElementById('revenueChart');
    if (!testCanvas) {
        console.warn('⚠️ Dashboard canvas elements not found. Charts may not render correctly.');
        console.log('⏳ Retrying in 200ms...');
        setTimeout(function() {
            const retryCanvas = document.getElementById('revenueChart');
            if (retryCanvas) {
                initDashboardCharts();
            } else {
                console.error('❌ Dashboard canvas elements still not found after retry');
            }
        }, 200);
        return;
    }
    
    // Initialize charts
    initializeCharts();
    window.refreshExistingChartsTheme?.();
    
    // Enhance chart animations after a delay
    setTimeout(() => {
        enhanceDashboardChartAnimations();
        console.log('✅ Dashboard chart animations enhanced!');
    }, 1000);
    
    function initializeCharts() {
        // Destroy existing charts if they exist
        if (window.dashboardCharts.revenueChart) {
            window.dashboardCharts.revenueChart.destroy();
        }
        if (window.dashboardCharts.occupancyChart) {
            window.dashboardCharts.occupancyChart.destroy();
        }
        if (window.dashboardCharts.bookingsChart) {
            window.dashboardCharts.bookingsChart.destroy();
        }
        if (window.dashboardCharts.userGrowthChart) {
            window.dashboardCharts.userGrowthChart.destroy();
        }
        if (window.dashboardCharts.hourBalanceChart) {
            window.dashboardCharts.hourBalanceChart.destroy();
        }
        if (window.dashboardCharts.avgRatingChart) {
            window.dashboardCharts.avgRatingChart.destroy();
        }
        if (window.dashboardCharts.guestBookingsTrendChart) {
            window.dashboardCharts.guestBookingsTrendChart.destroy();
        }
        
        const chartColors = getChartColors();
        
        // ====================================
        // REVENUE CHART (Line Chart)
        // ====================================
        const revenueCanvas = document.getElementById('revenueChart');
        if (revenueCanvas && window.dashboardData.revenueChart) {
            clearNoDataMessages('revenueChart');
            const revenueCtx = revenueCanvas.getContext('2d');
            const revenueData = window.dashboardData.revenueChart;

            var revenueValues = (revenueData && revenueData.data) ? revenueData.data : [];
            if (!hasAnyPositiveValue(revenueValues)) {
                showNoDataMessage('revenueChart', 'fas fa-chart-line', 'No Data Available', 'No revenue data found for the selected period');
            } else {
            
            window.dashboardCharts.revenueChart = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: revenueData.labels || [],
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: revenueData.data || [],
                        borderColor: chartColors.maroon,
                        backgroundColor: function(context) {
                            return createVerticalGradient(context.chart, chartColors.maroonFillTop, chartColors.maroonFillBottom);
                        },
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: chartColors.maroon,
                        pointBorderColor: chartColors.surfaceContrast,
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.dashboardAnimations.lineGrowth,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor,
                                font: { size: 12 },
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
            }
        }
        
        // ====================================
        // OCCUPANCY CHART (Doughnut Chart)
        // ====================================
        const occupancyCanvas = document.getElementById('occupancyChart');
        if (occupancyCanvas && window.dashboardData.occupancyChart) {
            clearNoDataMessages('occupancyChart');
            const occupancyCtx = occupancyCanvas.getContext('2d');
            const occupancyData = window.dashboardData.occupancyChart;

            var occValues = (occupancyData && occupancyData.data) ? occupancyData.data : [];
            if (!hasAnyPositiveValue(occValues)) {
                showNoDataMessage('occupancyChart', 'fas fa-chart-pie', 'No Data Available', 'No occupancy data found');
            } else {
            
            window.dashboardCharts.occupancyChart = new Chart(occupancyCtx, {
                type: 'doughnut',
                data: {
                    labels: occupancyData.labels || [],
                    datasets: [{
                        data: occupancyData.data || [],
                        backgroundColor: [
                            chartColors.series[0],
                            chartColors.series[3]
                        ],
                        borderColor: [
                            chartColors.borderColor,
                            chartColors.borderColor
                        ],
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.dashboardAnimations.doughnutModern,
                    cutout: '64%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 16,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
            }
        }
        
        // ====================================
        // BOOKINGS CHART (Bar Chart)
        // ====================================
        const bookingsCanvas = document.getElementById('bookingsChart');
        if (bookingsCanvas && window.dashboardData.bookingsChart) {
            clearNoDataMessages('bookingsChart');
            const bookingsCtx = bookingsCanvas.getContext('2d');
            const bookingsData = window.dashboardData.bookingsChart;

            var bookingValues = (bookingsData && bookingsData.data) ? bookingsData.data : [];
            if (!hasAnyPositiveValue(bookingValues)) {
                showNoDataMessage('bookingsChart', 'fas fa-chart-bar', 'No Data Available', 'No booking data found for the selected period');
            } else {
            
            window.dashboardCharts.bookingsChart = new Chart(bookingsCtx, {
                type: 'bar',
                data: {
                    labels: bookingsData.labels || [],
                    datasets: [{
                        label: 'Bookings',
                        data: bookingsData.data || [],
                        backgroundColor: function(context) {
                            return createVerticalGradient(context.chart, chartColors.accentBarTop, chartColors.accentBarBottom);
                        },
                        borderColor: chartColors.accent,
                        borderWidth: 1.2,
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.dashboardAnimations.barGrowth,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor,
                                font: { size: 12 }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: chartColors.tickColor,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
            }
        }
        
        // ====================================
        // USER GROWTH CHART (Line Chart)
        // ====================================
        const userGrowthCanvas = document.getElementById('userGrowthChart');
        if (userGrowthCanvas && window.dashboardData.userGrowthChart) {
            clearNoDataMessages('userGrowthChart');
            const userGrowthCtx = userGrowthCanvas.getContext('2d');
            const userGrowthData = window.dashboardData.userGrowthChart;
            
            // Calculate min and max for better y-axis scaling
            const growthValues = userGrowthData.data || [];

            if (!hasAnyPositiveValue(growthValues)) {
                showNoDataMessage('userGrowthChart', 'fas fa-users', 'No Data Available', 'No user growth data found for the selected period');
            } else {
            const minValue = Math.min(...growthValues, 0);
            const maxValue = Math.max(...growthValues);
            const range = maxValue - minValue;
            // If all values are the same or very close, use a sensible range
            const padding = range > 0 ? range * 0.1 : Math.max(1, maxValue * 0.1);
            
            window.dashboardCharts.userGrowthChart = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: userGrowthData.labels || [],
                    datasets: [{
                        label: 'Total Users',
                        data: growthValues,
                        borderColor: chartColors.gray,
                        backgroundColor: function(context) {
                            return createVerticalGradient(context.chart, chartColors.grayFillTop, chartColors.grayFillBottom);
                        },
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: chartColors.gray,
                        pointBorderColor: chartColors.surfaceContrast,
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.dashboardAnimations.lineGrowth,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Total Users: ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMin: Math.max(0, minValue - padding),
                            suggestedMax: maxValue + padding,
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor,
                                precision: 0,
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor
                            }
                        }
                    }
                }
            });
            }
        }
        
        // ====================================
        // HOUR BALANCE CHART (Bar Chart) - Optional
        // ====================================
        const hourBalanceCanvas = document.getElementById('hourBalanceChart');
        if (hourBalanceCanvas && window.dashboardData.hourBalanceTrends) {
            clearNoDataMessages('hourBalanceChart');
            const hourBalanceCtx = hourBalanceCanvas.getContext('2d');
            const hourBalanceData = window.dashboardData.hourBalanceTrends;

            var hourValues = (hourBalanceData && hourBalanceData.data) ? hourBalanceData.data : [];
            if (!hasAnyPositiveValue(hourValues)) {
                showNoDataMessage('hourBalanceChart', 'fas fa-clock', 'No Data Available', 'No hour balance data found');
            } else {
            
            window.dashboardCharts.hourBalanceChart = new Chart(hourBalanceCtx, {
                type: 'bar',
                data: {
                    labels: hourBalanceData.labels || [],
                    datasets: [{
                        label: 'Hours',
                        data: hourBalanceData.data || [],
                        backgroundColor: [
                            chartColors.series[0],
                            chartColors.series[1],
                            chartColors.series[2]
                        ],
                        borderColor: chartColors.borderColor,
                        borderWidth: 1.5,
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: chartColors.grid
                            },
                            ticks: {
                                color: chartColors.tickColor
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: chartColors.tickColor
                            }
                        }
                    }
                }
            });
            }
        }

        // ====================================
        // AVG RATING CHART (Line Chart)
        // ====================================
        var avgRatingCanvas = document.getElementById('avgRatingChart');
        if (avgRatingCanvas && window.dashboardData.avgRatingTrend) {
            clearNoDataMessages('avgRatingChart');
            var avgRatingCtx = avgRatingCanvas.getContext('2d');
            var avgRatingData = window.dashboardData.avgRatingTrend;

            var ratingValues = (avgRatingData && avgRatingData.data) ? avgRatingData.data : [];
            if (!hasAnyPositiveValue(ratingValues)) {
                showNoDataMessage('avgRatingChart', 'fas fa-star', 'No Data Available', 'No feedback ratings found for the selected period');
            } else {
                window.dashboardCharts.avgRatingChart = new Chart(avgRatingCtx, {
                    type: 'line',
                    data: {
                        labels: avgRatingData.labels || [],
                        datasets: [{
                            label: 'Average Rating',
                            data: avgRatingData.data || [],
                            borderColor: chartColors.amber,
                            backgroundColor: function(context) {
                                return createVerticalGradient(context.chart, chartColors.amberLight, 'rgba(139, 31, 43, 0.04)');
                            },
                            borderWidth: 2.5,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: chartColors.amber,
                            pointBorderColor: chartColors.surfaceContrast,
                            pointBorderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: window.dashboardAnimations.lineGrowth,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Avg Rating: ' + (context.parsed.y || 0);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                suggestedMax: 5,
                                grid: {
                                    color: chartColors.grid
                                },
                                ticks: {
                                    color: chartColors.tickColor,
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    color: chartColors.grid
                                },
                                ticks: {
                                    color: chartColors.tickColor
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // ====================================
        // GUEST BOOKINGS TREND CHART (Line Chart)
        // ====================================
        const guestBookingsTrendCanvas = document.getElementById('guestBookingsTrendChart');
        if (guestBookingsTrendCanvas && window.dashboardData.guestBookingsStats) {
            clearNoDataMessages('guestBookingsTrendChart');
            const guestBookingsTrendCtx = guestBookingsTrendCanvas.getContext('2d');
            const guestBookingsData = window.dashboardData.guestBookingsStats;
            
            const bookingsByDate = guestBookingsData.guest_bookings_by_date || [];
            const labels = bookingsByDate.map(item => item.label || item.date || '');
            const data = bookingsByDate.map(item => item.count || 0);
            
            if (data.length === 0 || !hasAnyPositiveValue(data)) {
                showNoDataMessage('guestBookingsTrendChart', 'fas fa-user-clock', 'No Data Available', 'No guest bookings data found for the selected period');
            } else {
                window.dashboardCharts.guestBookingsTrendChart = new Chart(guestBookingsTrendCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Guest Bookings',
                            data: data,
                            borderColor: chartColors.maroon,
                            backgroundColor: function(context) {
                                return createVerticalGradient(context.chart, chartColors.maroonFillTop, chartColors.maroonFillBottom);
                            },
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4.5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: chartColors.maroon,
                            pointBorderColor: chartColors.surfaceContrast,
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
                                backgroundColor: chartColors.tooltipBg,
                                titleColor: chartColors.tooltipText,
                                bodyColor: chartColors.tooltipText,
                                borderColor: chartColors.tooltipBorder,
                                borderWidth: 1,
                                padding: 12,
                                displayColors: false,
                                callbacks: {
                                    label: function(context) {
                                        return 'Bookings: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: chartColors.tickColor
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: chartColors.grid,
                                    borderDash: [5, 5]
                                },
                                ticks: {
                                    color: chartColors.tickColor,
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }
        
        console.log('✅ All charts initialized');
    }
}

// Set up initPageScripts to handle both dashboard and analytics
// This will be called from page views
if (typeof window.initPageScripts === 'undefined') {
    window.initPageScripts = function() {
        // Check if we're on dashboard page (has dashboardData)
        if (typeof window.dashboardData !== 'undefined') {
            initDashboardCharts();
            return;
        }
        
        // Check if we're on analytics page (has analyticsData)
        if (typeof window.analyticsData !== 'undefined') {
            // analytics.js will handle this
            return;
        }
    };
} else {
    // If analytics.js already defined it, enhance it to check for dashboard too
    const originalInit = window.initPageScripts;
    window.initPageScripts = function() {
        // Check if we're on analytics page first by checking for canvas elements
        const analyticsCanvas = document.getElementById('revenuePlanChart');
        if (analyticsCanvas && typeof window.analyticsData !== 'undefined') {
            // Clean up any dashboard charts that might exist
            if (typeof window.dashboardCharts !== 'undefined') {
                Object.keys(window.dashboardCharts).forEach(key => {
                    const chart = window.dashboardCharts[key];
                    if (chart) {
                        try {
                            chart.destroy();
                        } catch (error) {
                            console.warn(`⚠️ Error destroying dashboard chart ${key}:`, error);
                        }
                        window.dashboardCharts[key] = null;
                    }
                });
            }
            // Let analytics.js handle it (it will call originalInit if needed)
            if (originalInit && typeof originalInit === 'function') {
                originalInit();
            }
            return;
        }
        
        // Check if we're on dashboard page by checking for dashboard-specific canvas
        const dashboardCanvas = document.getElementById('revenueChart');
        if (dashboardCanvas && typeof window.dashboardData !== 'undefined') {
            // Clean up analytics charts if they exist
            if (typeof window.analyticsCharts !== 'undefined') {
                Object.keys(window.analyticsCharts).forEach(key => {
                    const chart = window.analyticsCharts[key];
                    if (chart) {
                        try {
                            chart.destroy();
                        } catch (error) {
                            console.warn(`⚠️ Error destroying analytics chart ${key}:`, error);
                        }
                        window.analyticsCharts[key] = null;
                    }
                });
            }
            initDashboardCharts();
            return;
        }
        
        // Otherwise call original
        if (originalInit && typeof originalInit === 'function') {
            originalInit();
        }
    };
}

// Global function to load dashboard with filter (AJAX)
// Only updates the dashboard content, not the filter component
function loadDashboardWithFilter(filter, startDate = null, endDate = null) {
    let url = BASE_URL + 'dashboard?filter=' + filter + '&filter_change=1';
    
    if (filter === 'custom' && startDate && endDate) {
        url += '&start_date=' + startDate + '&end_date=' + endDate;
        console.log('🔄 Loading dashboard with custom filter:', filter, 'from', startDate, 'to', endDate);
    } else {
        console.log('🔄 Loading dashboard with filter:', filter);
    }
    
    // Show loading animation in dashboard content area only
    $('#dashboardContent').html(`
        <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Dashboard...</h5>
                <p class="text-muted">Please wait while we update your data</p>
            </div>
        </div>
    `);
    
    // Load new data via AJAX
    $.ajax({
        url: url,
        type: 'GET',
        timeout: 10000,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(html) {
            // Replace only the dashboard content (not the filter)
            $('#dashboardContent').html(html);
            
            // Reset dashboard initialization flag for reinitialization
            resetDashboardInitialization();
            
            // Update filter component display if custom dates were used
            // Use setTimeout to ensure DOM is ready
            setTimeout(function() {
                if (filter === 'custom' && startDate && endDate) {
                    console.log('Updating filter display with custom dates:', startDate, endDate);
                    if (typeof window.updateFilterDisplay === 'function') {
                        window.updateFilterDisplay('custom', startDate, endDate);
                    } else {
                        console.error('updateFilterDisplay function not found!');
                    }
                } else if (filter) {
                    if (typeof window.updateFilterDisplay === 'function') {
                        window.updateFilterDisplay(filter, null, null);
                    }
                }
            }, 100);
            
            // Wait a moment for the script tags to execute (window.dashboardData to be set)
            // Then re-initialize page scripts (which will recreate charts with new data)
            setTimeout(function() {
                if (typeof window.initPageScripts === 'function') {
                    window.initPageScripts();
                } else {
                    console.error('❌ initPageScripts not found after loading dashboard content!');
                }
                
                // Stabilize layout after content loads to prevent jumping
                if (typeof window.stabilizeLayout === 'function') {
                    window.stabilizeLayout();
                }
            }, 150);
            
            console.log('✅ Dashboard updated with filter:', filter || 'today');
        },
        error: function(xhr, status, error) {
            let errorMessage = 'Failed to update dashboard';
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error. Please refresh the page.';
            }
            
            console.error('❌ Dashboard update failed:', error);
            $('#dashboardContent').html(`
                <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
                    <div class="text-center">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-triangle fa-3x"></i>
                        </div>
                        <h5 class="text-danger mb-2">Error Loading Dashboard</h5>
                        <p class="text-muted mb-3">${errorMessage}</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh me-2"></i>Refresh Page
                        </button>
                    </div>
                </div>
            `);
        }
    });
}

