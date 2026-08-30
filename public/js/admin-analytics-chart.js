(function () {
    var host = document.querySelector('[data-visits-chart]');

    if (!host || typeof Chart === 'undefined') {
        // Sans JavaScript — ou sans Chart.js — le tableau rendu côté serveur
        // porte déjà chaque valeur. Il n'y a rien à réparer ici.
        return;
    }

    var canvas = host.querySelector('canvas');
    var chart = null;

    function token(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function parse(attribute) {
        try {
            return JSON.parse(host.getAttribute(attribute)) || [];
        } catch (error) {
            return [];
        }
    }

    function build() {
        var humans = token('--chart-series-1');
        var bots = token('--chart-previous');
        var grid = token('--chart-grid');
        var text = token('--text-muted');

        chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: parse('data-labels'),
                datasets: [
                    { label: 'Humans', data: parse('data-humans'), backgroundColor: humans, stack: 'visits' },
                    { label: 'Bots', data: parse('data-bots'), backgroundColor: bots, stack: 'visits' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { color: text, maxTicksLimit: 16 } },
                    y: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { color: text, precision: 0 } },
                },
            },
        });
    }

    build();

    // Les couleurs viennent des variables CSS : au changement de thème, le
    // graphique se redessine avec la nouvelle palette.
    new MutationObserver(function () {
        if (chart) {
            chart.destroy();
        }

        build();
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
})();
