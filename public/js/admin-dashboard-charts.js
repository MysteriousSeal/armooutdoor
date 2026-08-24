(function () {
    var host = document.querySelector('[data-revenue-chart]');

    if (!host || typeof Chart === 'undefined') {
        // Sans JavaScript — ou sans Chart.js — le tableau rendu côté serveur
        // porte déjà chaque valeur. Il n'y a rien à réparer ici.
        return;
    }

    var canvas = host.querySelector('canvas');
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

    function parse(attribute) {
        try {
            return JSON.parse(host.getAttribute(attribute)) || [];
        } catch (error) {
            return [];
        }
    }

    function euros(value) {
        return value.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function build() {
        var colors = palette();
        var labels = parse('data-labels');
        var current = parse('data-current');
        var previous = parse('data-previous');

        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
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
                        tension: 0.25,
                    },
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
                        tension: 0.25,
                    },
                ],
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

        chart.data.datasets[0].borderColor = colors.previous;
        chart.data.datasets[0].pointHoverBorderColor = colors.surface;
        chart.data.datasets[0].pointHoverBackgroundColor = colors.previous;

        chart.data.datasets[1].borderColor = colors.current;
        chart.data.datasets[1].backgroundColor = 'color-mix(in srgb, ' + colors.current + ' 10%, transparent)';
        chart.data.datasets[1].pointHoverBorderColor = colors.surface;
        chart.data.datasets[1].pointHoverBackgroundColor = colors.current;

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

    build();

    // Le sélecteur de thème bascule data-theme sur <html> : sans écouter ce
    // changement, passer en sombre laisserait un graphique en couleurs claires.
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.attributeName === 'data-theme') {
                recolor();
            }
        });
    }).observe(document.documentElement, { attributes: true });
})();
