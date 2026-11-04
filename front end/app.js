const seedItems = [
    { id: 1, type: 'found', name: 'Blue water bottle', category: 'Accessories', description: 'Navy insulated bottle with a small mountain sticker on the side.', location: 'Central Library', date: '2026-10-30', reporter: 'A. Student', mine: false, recovered: false },
    { id: 2, type: 'lost', name: 'Wireless earbuds case', category: 'Electronics', description: 'White case, possibly left near the food court after lunch.', location: 'Food Court', date: '2026-10-29', reporter: 'J. Patel', mine: false, recovered: false },
    { id: 3, type: 'found', name: 'Set of silver keys', category: 'Keys', description: 'Three keys on a green fabric key ring. Handed in near reception.', location: 'Main Entrance', date: '2026-10-28', reporter: 'M. Chen', mine: false, recovered: false },
    { id: 4, type: 'lost', name: 'Calculus notebook', category: 'Books & Notes', description: 'Blue spiral notebook with lecture notes inside.', location: 'CSE Block', date: '2026-10-26', reporter: 'Sam Taylor', mine: true, recovered: false },
    { id: 5, type: 'found', name: 'Black backpack', category: 'Bags', description: 'Small black backpack found in a classroom after the afternoon lecture.', location: 'Main Block', date: '2026-10-25', reporter: 'R. Singh', mine: false, recovered: false },
    { id: 6, type: 'lost', name: 'Green campus hoodie', category: 'Clothing & Accessories', description: 'Green hoodie with a campus logo on the front.', location: 'Hostel', date: '2026-10-22', reporter: 'Sam Taylor', mine: true, recovered: true },
];

const seedNotifications = [
    { title: 'Possible match found', message: 'A blue water bottle was reported near the Central Library. Take a look at the campus board.', date: 'Oct 30, 2026', unread: true },
    { title: 'Welcome to CampusFind', message: 'Thanks for helping your campus community reunite people with their belongings.', date: 'Oct 28, 2026', unread: true },
    { title: 'Report marked recovered', message: 'Your green campus hoodie report has been marked as recovered.', date: 'Oct 25, 2026', unread: false },
];

const storeKey = 'campusfind-static-demo-v1';
let state = loadState();
let activityChart;

function loadState() {
    try {
        const saved = JSON.parse(localStorage.getItem(storeKey));
        if (saved && Array.isArray(saved.items) && Array.isArray(saved.notifications)) {
            return saved;
        }
    } catch (error) {
        console.warn('Could not load saved demo data; using sample data.', error);
    }
    return { items: seedItems.map((item) => ({ ...item })), notifications: seedNotifications.map((item) => ({ ...item })) };
}

function saveState() {
    try {
        localStorage.setItem(storeKey, JSON.stringify(state));
    } catch (error) {
        console.error('Could not save demo data to this browser.', error);
        window.alert('Your browser could not save this change. Check browser storage settings.');
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
}

function itemCard(item, showRecovery = false) {
    const typeLabel = item.type === 'lost' ? 'Lost item' : 'Found item';
    const date = new Date(`${item.date}T12:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return `<article class="item-card">
        <div class="item-photo-placeholder" aria-hidden="true">${item.type === 'lost' ? '↗' : '✓'}</div>
        <div class="item-card-body">
            <div class="item-card-top"><span class="badge ${item.type === 'lost' ? 'badge-lost' : 'badge-found'}">${typeLabel}</span>${item.recovered ? '<span class="status-text">Recovered</span>' : ''}</div>
            <h3>${escapeHtml(item.name)}</h3>
            <p class="item-description">${escapeHtml(item.description)}</p>
            <div class="item-meta"><span>${escapeHtml(item.category)}</span><span>${escapeHtml(item.location)}</span><span>${escapeHtml(date)}</span></div>
            <div class="item-card-footer"><span>Reported by ${escapeHtml(item.reporter)}</span><span>${item.recovered ? 'Recovered' : item.type === 'found' ? 'Private inquiry' : 'Open report'}</span></div>
            ${showRecovery && item.mine && !item.recovered ? `<button class="text-button recovery-button" type="button" data-recover="${item.id}">Mark recovered</button>` : ''}
        </div>
    </article>`;
}

function renderItems() {
    const activeItems = state.items.filter((item) => !item.recovered);
    document.getElementById('recent-items').innerHTML = activeItems.slice(0, 4).map((item) => itemCard(item)).join('');
    document.getElementById('my-items').innerHTML = state.items.filter((item) => item.mine).map((item) => itemCard(item, true)).join('')
        || '<div class="empty-state"><span class="empty-icon">▤</span><h3>Your reports will appear here</h3><p>Use the report form to add a demo report.</p></div>';
    document.getElementById('lost-count').textContent = activeItems.filter((item) => item.type === 'lost').length;
    document.getElementById('found-count').textContent = activeItems.filter((item) => item.type === 'found').length;
    document.getElementById('my-count').textContent = state.items.filter((item) => item.mine).length;
    document.getElementById('recovered-count').textContent = state.items.filter((item) => item.mine && item.recovered).length;
    document.getElementById('pending-count').textContent = state.items.filter((item) => !item.mine).length;
    document.getElementById('admin-rows').innerHTML = state.items.slice(0, 6).map((item) => `<tr><td><strong>${escapeHtml(item.name)}</strong><span class="table-sub">${escapeHtml(item.category)}</span></td><td>${item.type === 'lost' ? 'Lost' : 'Found'}</td><td>${escapeHtml(item.location)}</td><td><span class="review-badge ${item.mine ? 'review-approved' : 'review-pending'}">${item.mine ? 'Approved' : 'Pending'}</span></td></tr>`).join('');
    document.getElementById('nav-count').hidden = !state.notifications.some((notification) => notification.unread);
    renderBrowse();
    renderNotifications();
}

function renderBrowse() {
    const search = document.getElementById('search-items').value.trim().toLowerCase();
    const type = document.getElementById('filter-type').value;
    const category = document.getElementById('filter-category').value;
    const items = state.items.filter((item) => {
        const text = `${item.name} ${item.description} ${item.location} ${item.category}`.toLowerCase();
        return (!search || text.includes(search)) && (!type || item.type === type) && (!category || item.category === category);
    });
    document.getElementById('browse-items').innerHTML = items.map((item) => itemCard(item)).join('')
        || '<div class="empty-state"><span class="empty-icon">⌕</span><h3>No reports found</h3><p>Try changing your search or filters.</p></div>';
    document.getElementById('results-line').textContent = `${items.length} ${items.length === 1 ? 'report' : 'reports'} shown`;
}

function renderNotifications() {
    const list = document.getElementById('notification-list');
    list.innerHTML = state.notifications.map((notification) => `<article class="notification-row ${notification.unread ? 'unread' : ''}">
        <span class="notification-icon">${notification.unread ? '✦' : '○'}</span>
        <div class="notification-copy"><strong>${escapeHtml(notification.title)}</strong><p>${escapeHtml(notification.message)}</p><small>${escapeHtml(notification.date)}</small></div>
    </article>`).join('') || '<div class="empty-state"><h3>You are all caught up</h3><p>New demo notifications will appear here.</p></div>';
    document.getElementById('mark-read').hidden = !state.notifications.some((notification) => notification.unread);
}

function showPage(name) {
    const pageNames = ['dashboard', 'browse', 'report', 'my-reports', 'notifications', 'admin'];
    const page = pageNames.includes(name) ? name : 'dashboard';
    document.querySelectorAll('.static-page').forEach((section) => { section.hidden = section.dataset.page !== page; });
    document.querySelectorAll('[data-page-link]').forEach((link) => {
        link.classList.toggle('active', link.dataset.pageLink === page);
    });
    document.getElementById('breadcrumb-current').textContent = page === 'my-reports' ? 'My reports' : page === 'admin' ? 'Admin preview' : page[0].toUpperCase() + page.slice(1);
    if (location.hash !== `#${page}`) {
        history.replaceState(null, '', `#${page}`);
    }
    if (page === 'browse') renderBrowse();
}

function initChart() {
    const canvas = document.getElementById('activity-chart');
    if (!canvas || typeof Chart === 'undefined') return;
    activityChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'],
            datasets: [
                { label: 'Lost', data: [6, 8, 5, 10, 7, 4], backgroundColor: '#f0a06a', borderRadius: 5, maxBarThickness: 24 },
                { label: 'Found', data: [8, 5, 9, 7, 11, 6], backgroundColor: '#62b894', borderRadius: 5, maxBarThickness: 24 },
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
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-page-link]');
    if (link) {
        event.preventDefault();
        showPage(link.dataset.pageLink);
    }
    const recoveryButton = event.target.closest('[data-recover]');
    if (recoveryButton) {
        const item = state.items.find((entry) => entry.id === Number(recoveryButton.dataset.recover));
        if (item) {
            item.recovered = true;
            state.notifications.unshift({ title: 'Report marked recovered', message: `Your ${item.name} report has been marked as recovered.`, date: new Date().toLocaleDateString(), unread: true });
            saveState();
            renderItems();
        }
    }
});

document.getElementById('filter-form').addEventListener('submit', (event) => {
    event.preventDefault();
    renderBrowse();
});
document.getElementById('report-form').addEventListener('submit', (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const values = new FormData(form);
    state.items.unshift({
        id: Date.now(),
        type: values.get('type'),
        name: values.get('name').trim(),
        category: values.get('category'),
        description: values.get('description').trim(),
        location: values.get('location'),
        date: values.get('date'),
        reporter: 'Sam Taylor',
        mine: true,
        recovered: false,
    });
    saveState();
    renderItems();
    form.reset();
    showPage('my-reports');
});
document.getElementById('mark-read').addEventListener('click', () => {
    state.notifications.forEach((notification) => { notification.unread = false; });
    saveState();
    renderNotifications();
    renderItems();
});
window.addEventListener('hashchange', () => showPage(location.hash.slice(1)));
document.getElementById('search-items').addEventListener('input', renderBrowse);
document.getElementById('filter-type').addEventListener('change', renderBrowse);
document.getElementById('filter-category').addEventListener('change', renderBrowse);

renderItems();
initChart();
showPage(location.hash.slice(1) || 'dashboard');
