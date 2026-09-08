(function () {
    var host = document.querySelector('[data-revenue-chart]');

    if (typeof Chart === 'undefined') {
        // Sans JavaScript — ou sans Chart.js — le tableau rendu côté serveur
        // porte déjà chaque valeur. Il n'y a rien à réparer ici.
        return;
    }

    var canvas = host ? host.querySelector('canvas') : null;
    var chart = null;

    // Les couleurs viennent des variables CSS, jamais d'hexadécimaux écrits
    // ici : deux sources pour la même teinte finiraient par diverger.
    function token(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function palette() {
        return {
            current: token('--chart-series-1'),
            previous: token('--chart-previous'),
            grid: token('--chart-grid'),
            text: token('--text-muted'),
            surface: token('--chart-surface'),
        };
    }

    function read(element, attribute) {
        try {
            return JSON.parse(element.getAttribute(attribute)) || [];
        } catch (error) {
            return [];
        }
    }

    function parse(attribute) {
        return read(host, attribute);
    }

    function euros(value) {
        return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function build() {
        if (!canvas) {
            return;
        }

        var colors = palette();
        var labels = parse('data-labels');
        var current = parse('data-current');
        var previous = parse('data-previous');

        // A smoothed curve between two days is still a reading; between two
        // months it invents a dip and a rise nobody measured, so monthly
        // points are joined straight.
        var tension = host.getAttribute('data-bucket') === 'month' ? 0 : 0.25;

        // "All time" has no previous window: without this, the ghost series
        // would stay in the tooltip at zero euros everywhere.
        var ghost = previous.length > 0 ? [{
            label: 'Previous period',
            data: previous,
            borderColor: colors.previous,
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBorderWidth: 2,
            pointHoverBorderColor: colors.surface,
            pointHoverBackgroundColor: colors.previous,
            tension: tension,
        }] : [];

        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: ghost.concat([
                    {
                        label: 'Current period',
                        data: current,
                        borderColor: colors.current,
                        // Un lavis, jamais un aplat saturé.
                        backgroundColor: 'color-mix(in srgb, ' + colors.current + ' 10%, transparent)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBorderWidth: 2,
                        pointHoverBorderColor: colors.surface,
                        pointHoverBackgroundColor: colors.current,
                        tension: tension,
                    },
                ]),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // Cible de survol plus large que la marque elle-même.
                interaction: { mode: 'index', intersect: false },
                elements: { line: { borderJoinStyle: 'round', borderCapStyle: 'round' } },
                plugins: {
                    // La légende vit dans le HTML, où elle reste lisible sans
                    // JavaScript et garde l'encre du thème.
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.surface,
                        titleColor: token('--text'),
                        bodyColor: token('--text'),
                        borderColor: colors.grid,
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ' : ' + euros(context.parsed.y);
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: colors.grid },
                        ticks: { color: colors.text, maxRotation: 0, autoSkipPadding: 16 },
                    },
                    y: {
                        beginAtZero: true,
                        // Filets pleins, d'un cran sur le fond : jamais de
                        // pointillés, qui font vibrer la grille.
                        grid: { color: colors.grid, drawTicks: false },
                        border: { display: false },
                        ticks: {
                            color: colors.text,
                            padding: 8,
                            callback: function (value) {
                                return value.toLocaleString('fr-FR') + ' €';
                            },
                        },
                    },
                },
            },
        });
    }

    function recolor() {
        if (!chart) {
            return;
        }

        var colors = palette();

        // Found by name: with no previous window the current series is the
        // first one, and a hard-coded index would paint it grey.
        chart.data.datasets.forEach(function (dataset) {
            var isGhost = dataset.label === 'Previous period';
            var hue = isGhost ? colors.previous : colors.current;

            dataset.borderColor = hue;
            dataset.pointHoverBorderColor = colors.surface;
            dataset.pointHoverBackgroundColor = hue;

            if (!isGhost) {
                dataset.backgroundColor = 'color-mix(in srgb, ' + hue + ' 10%, transparent)';
            }
        });

        chart.options.plugins.tooltip.backgroundColor = colors.surface;
        chart.options.plugins.tooltip.titleColor = token('--text');
        chart.options.plugins.tooltip.bodyColor = token('--text');
        chart.options.plugins.tooltip.borderColor = colors.grid;

        chart.options.scales.x.border.color = colors.grid;
        chart.options.scales.x.ticks.color = colors.text;
        chart.options.scales.y.grid.color = colors.grid;
        chart.options.scales.y.ticks.color = colors.text;

        chart.update('none');
    }

    // The count of orders per day. Bars rather than a line: a quantity
    // counted per interval does not flow from one day to the next, and a
    // line joining them invents a value between two points.
    var ordersHost = document.querySelector('[data-orders-chart]');
    var ordersChart = null;

    function buildOrders() {
        if (!ordersHost) {
            return;
        }

        var ordersCanvas = ordersHost.querySelector('canvas');

        if (!ordersCanvas) {
            return;
        }

        var colors = palette();

        ordersChart = new Chart(ordersCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: read(ordersHost, 'data-labels'),
                datasets: [{
                    label: 'Orders',
                    data: read(ordersHost, 'data-current'),
                    backgroundColor: colors.current,
                    hoverBackgroundColor: colors.current,
                    borderWidth: 0,
                    // Square bars, like everything else in the back-office.
                    borderRadius: 0,
                    maxBarThickness: 18,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.surface,
                        titleColor: token('--text'),
                        bodyColor: token('--text'),
                        borderColor: colors.grid,
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                var count = context.parsed.y;

                                return count + (count === 1 ? ' order' : ' orders');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { color: colors.grid },
                        ticks: { color: colors.text, maxRotation: 0, autoSkipPadding: 20 },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.grid, drawTicks: false },
                        border: { display: false },
                        // Orders are counted: no half a tick.
                        ticks: { color: colors.text, padding: 8, precision: 0 },
                    },
                },
            },
        });
    }

    function recolorOrders() {
        if (!ordersChart) {
            return;
        }

        var colors = palette();

        ordersChart.data.datasets[0].backgroundColor = colors.current;
        ordersChart.data.datasets[0].hoverBackgroundColor = colors.current;

        ordersChart.options.plugins.tooltip.backgroundColor = colors.surface;
        ordersChart.options.plugins.tooltip.titleColor = token('--text');
        ordersChart.options.plugins.tooltip.bodyColor = token('--text');
        ordersChart.options.plugins.tooltip.borderColor = colors.grid;

        ordersChart.options.scales.x.border.color = colors.grid;
        ordersChart.options.scales.x.ticks.color = colors.text;
        ordersChart.options.scales.y.grid.color = colors.grid;
        ordersChart.options.scales.y.ticks.color = colors.text;

        ordersChart.update('none');
    }

    build();
    buildOrders();

    // Le sélecteur de thème bascule data-theme sur <html> : sans écouter ce
    // changement, passer en sombre laisserait un graphique en couleurs claires.
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'data-theme') {
                recolor();
                recolorOrders();
            }
        });
    }).observe(document.documentElement, { attributes: true });
})();
