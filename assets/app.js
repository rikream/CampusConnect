document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.files?.[0]) {
        return;
    }

    const file = input.files[0];
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type) || file.size > 3 * 1024 * 1024) {
        input.setCustomValidity('Choose a JPG, PNG, or WebP image no larger than 3 MB.');
        input.reportValidity();
        input.value = '';
        return;
    }

    input.setCustomValidity('');
});

document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('activity-chart');
    const chartData = window.campusFindChartData;
    if (!canvas || !chartData || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                { label: 'Lost', data: chartData.lost, backgroundColor: '#f0a06a', borderRadius: 5, maxBarThickness: 24 },
                { label: 'Found', data: chartData.found, backgroundColor: '#62b894', borderRadius: 5, maxBarThickness: 24 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, color: '#758198' } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#8a96a8' } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#8a96a8' }, grid: { color: '#eef1f5' } },
            },
        },
    });
});
