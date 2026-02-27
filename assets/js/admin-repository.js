/**
 * Script for admin repository page.
 */
class SmliserAdminRepository {
    /**
     * Class constructor
     * 
     * @param {HTMLElement} baseEl The repository base element.
     */
    constructor( baseEl ) {
        this.baseEl = baseEl;

        this._cacheElements();
        this._bindEvents();
        
    }

    /**
     * Cache elements
     */
    _cacheElements() {
        if ( ! this.baseEl ) return;

        this.galleryPreviewSection  = this.baseEl.querySelector( '.smliser-screenshot-gallery' );
        this.allPreviewImages       = this.galleryPreviewSection.querySelectorAll( 'img.repo-image-preview' );
        this.currentPreviewImage    = this.galleryPreviewSection.querySelector( '.smliser-gallery-preview_image img' );
        this.currentImageTitle      = this.galleryPreviewSection.querySelector( '.smliser-gallery-preview_title' );
        this.allMiniAnalytics       = document.querySelectorAll( 'canvas.smliser-app-mini-analytics' );
    }

    /**
     * Bind eventlisteners
     */
    _bindEvents() {
        this.galleryPreviewSection?.addEventListener( 'click', this.handleGalleryClicks.bind(this) );
        this.galleryPreviewSection?.addEventListener( 'dblclick', this.toggleImageFullScreen.bind(this) );
        this.allMiniAnalytics.forEach( canvas => this._buildChart(canvas) );
    }

    /**
     * Handle clicks on the gallery preview section of the repository
     * 
     * @param {Event} ev The event object.
     */
    handleGalleryClicks( ev ) {
        const clickedImage = ev.target.closest( 'img.repo-image-preview' );
        if ( ! clickedImage ) return;
        
        this.allPreviewImages.forEach( img => img.classList.remove( 'active' ) );
        this.currentPreviewImage?.setAttribute( 'src', clickedImage.src );
        this.currentImageTitle.textContent = clickedImage.getAttribute( 'data-repo-image-title' );
        clickedImage.classList.add( 'active' );

        
    }

    /**
     * Toggle image fullscreen mode
     * 
     * @param {Event} ev
     */
    toggleImageFullScreen( ev ) {
        const mainImage = ev.target.closest( '.smliser-request-fullscreen' );

        if ( mainImage ) {
            mainImage.requestFullscreen();
        }
    }

    /**
     * Build chart with custom HTML tooltip and stats footer.
     *
     * @param {HTMLCanvasElement} canvas
     */
    _buildChart(canvas) {
        const ctx = canvas.getContext('2d');
        const rawData = canvas.getAttribute('data-analytics');

        if (!rawData) {
            return;
        }

        let analytics;
        try {
            analytics = StringUtils.JSONparse(rawData);
        } catch (e) {
            console.error('Failed to parse analytics data:', e);
            return;
        }

        const datasets = [];
        let labels = [];

        // ---------------------------
        // Downloads as Bar Chart
        // ---------------------------
        if (
            analytics.downloads?.timeline?.days?.length &&
            analytics.downloads?.timeline?.values?.length
        ) {
            labels = analytics.downloads.timeline.days;

            datasets.push({
                type: 'bar',
                label: 'Downloads',
                data: analytics.downloads.timeline.values,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(59, 130, 246, 0.95)',
                yAxisID: 'y'
            });
        }

        // ---------------------------
        // Client Access as Line Chart
        // ---------------------------
        if (
            analytics.client_access?.timeline?.days?.length &&
            analytics.client_access?.timeline?.values?.length
        ) {
            if (!labels.length) {
                labels = analytics.client_access.timeline.days;
            }

            datasets.push({
                type: 'line',
                label: 'Client Access',
                data: analytics.client_access.timeline.values,
                borderColor: 'rgba(16, 185, 129, 1)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: false,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: 'rgba(16, 185, 129, 1)',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 3,
                yAxisID: 'y1'
            });
        }

        if (!datasets.length) {
            canvas.parentElement.innerHTML = '<p style="text-align:center;color:#999;">No analytics data available</p>';
            return;
        }

        this._renderComboChart(ctx, labels, datasets, analytics, canvas);
    }

    /**
     * Render combined bar + line chart with custom HTML tooltip.
     *
     * @param {CanvasRenderingContext2D} ctx
     * @param {Array} labels
     * @param {Array} datasets
     * @param {Object} analytics
     * @param {HTMLCanvasElement} canvas
     */
    _renderComboChart(ctx, labels, datasets, analytics, canvas) {
        const formattedLabels = labels.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        // Create custom tooltip element
        const getOrCreateTooltip = (chart) => {
            let tooltipEl = chart.canvas.parentNode.querySelector('.chartjs-tooltip');

            if (!tooltipEl) {
                tooltipEl = document.createElement('div');
                tooltipEl.className = 'chartjs-tooltip';
                tooltipEl.style.cssText = `
                    position: absolute;
                    background: linear-gradient(135deg, rgba(30, 30, 46, 0.98) 0%, rgba(20, 20, 32, 0.98) 100%);
                    border-radius: 12px;
                    color: white;
                    opacity: 0;
                    pointer-events: none;
                    transform: translate(-50%, 0);
                    transition: all 0.2s ease;
                    padding: 0;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    z-index: 1000;
                    min-width: 280px;
                    backdrop-filter: blur(10px);
                `;

                const table = document.createElement('div');
                table.style.margin = '0';
                table.style.padding = '0';

                tooltipEl.appendChild(table);
                chart.canvas.parentNode.appendChild(tooltipEl);
            }

            return tooltipEl;
        };

        // External tooltip handler
        const externalTooltipHandler = (context) => {
            const { chart, tooltip } = context;
            const tooltipEl = getOrCreateTooltip(chart);

            // Hide if no tooltip
            if (tooltip.opacity === 0) {
                tooltipEl.style.opacity = 0;
                return;
            }

            // Build tooltip content
            if (tooltip.body) {
                const tableRoot = tooltipEl.querySelector('div');
                tableRoot.innerHTML = '';

                // Header with date
                const header = document.createElement('div');
                header.style.cssText = `
                    padding: 14px 16px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    font-weight: 600;
                    font-size: 14px;
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 12px 12px 0 0;
                `;
                header.innerHTML = `<div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px;">📅</span>
                    <span>${labels[tooltip.dataPoints[0].dataIndex]}</span>
                </div>`;
                tableRoot.appendChild(header);

                // Metrics section
                const metricsContainer = document.createElement('div');
                metricsContainer.style.cssText = 'padding: 16px; display: flex; flex-direction: column; gap: 12px;';

                tooltip.dataPoints.forEach((dataPoint, i) => {
                    const colors = tooltip.labelColors[i];
                    const metricDiv = document.createElement('div');
                    metricDiv.style.cssText = 'display: flex; align-items: center; justify-content: space-between;';

                    const label = dataPoint.dataset.label;
                    const value = dataPoint.parsed.y.toLocaleString();
                    const color = colors.backgroundColor;

                    metricDiv.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="
                                width: 12px; 
                                height: 12px; 
                                border-radius: 3px; 
                                background: ${color};
                                display: inline-block;
                                box-shadow: 0 0 8px ${color}40;
                            "></span>
                            <span style="font-size: 13px; color: rgba(255, 255, 255, 0.7);">${label}</span>
                        </div>
                        <span style="font-weight: 700; font-size: 16px;">${value}</span>
                    `;

                    metricsContainer.appendChild(metricDiv);
                });

                tableRoot.appendChild(metricsContainer);

                // Calculate ratio if both metrics exist
                if (tooltip.dataPoints.length === 2) {
                    const downloads = tooltip.dataPoints.find(dp => dp.dataset.label === 'Downloads')?.parsed.y || 0;
                    const access = tooltip.dataPoints.find(dp => dp.dataset.label === 'Client Access')?.parsed.y || 0;

                    if (downloads > 0 && access > 0) {
                        const ratio = (access / downloads).toFixed(2);
                        const ratioDiv = document.createElement('div');
                        ratioDiv.style.cssText = `
                            padding: 12px 16px;
                            border-top: 1px solid rgba(255, 255, 255, 0.1);
                            background: rgba(99, 102, 241, 0.1);
                            border-radius: 0 0 12px 12px;
                            text-align: center;
                            font-size: 12px;
                            color: rgba(255, 255, 255, 0.8);
                        `;
                        ratioDiv.innerHTML = `
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <span>📊</span>
                                <span>Access/Download Ratio: <strong style="color: #818cf8;">${ratio}x</strong></span>
                            </div>
                        `;
                        tableRoot.appendChild(ratioDiv);
                    }
                }
            }

            const { offsetLeft: positionX, offsetTop: positionY } = chart.canvas;

            // Smart positioning to prevent overflow
            const tooltipWidth = tooltipEl.offsetWidth;
            const tooltipHeight = tooltipEl.offsetHeight;
            const canvasRect = chart.canvas.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            
            // Calculate initial position (centered above point)
            let left = positionX + tooltip.caretX;
            let top = positionY + tooltip.caretY;
            let transformX = '-50%';
            let transformY = '-110%';

            // Check horizontal overflow
            const absoluteLeft = canvasRect.left + tooltip.caretX;
            const tooltipHalfWidth = tooltipWidth / 2;

            if (absoluteLeft - tooltipHalfWidth < 10) {
                transformX = '0%';
                left = positionX + tooltip.caretX;
            } else if (absoluteLeft + tooltipHalfWidth > viewportWidth - 10) {
                transformX = '-100%';
                left = positionX + tooltip.caretX;
            }

            // Check vertical overflow
            const absoluteTop = canvasRect.top + tooltip.caretY;
            
            if (absoluteTop - tooltipHeight - 20 < 10) {
                transformY = '10%';
            }

            // Apply position
            tooltipEl.style.opacity = 1;
            tooltipEl.style.left = left + 'px';
            tooltipEl.style.top = top + 'px';
            tooltipEl.style.transform = `translate(${transformX}, ${transformY})`;
        };

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: formattedLabels,
                datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    tooltip: {
                        enabled: false,
                        external: externalTooltipHandler
                    },
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Downloads vs Client Access (Last 30 Days)',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            bottom: 20
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10,
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Downloads',
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            color: 'rgba(59, 130, 246, 1)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            callback: function(value) {
                                return value.toLocaleString();
                            },
                            font: {
                                size: 11
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Client Access',
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            color: 'rgba(16, 185, 129, 1)'
                        },
                        grid: {
                            drawOnChartArea: false,
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0,
                            callback: function(value) {
                                return value.toLocaleString();
                            },
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 750,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
}

document.addEventListener( 'DOMContentLoaded', (e) => {
    const repoBase = document.querySelector( '.smliser-admin-repository-template.repo-page' );

    if ( repoBase ) {
        new SmliserAdminRepository( repoBase );
    }
    
    
});