// =========================================================
// API HELPER
// =========================================================

async function api(endpoint, options = {}) {
    const response = await fetch(endpoint, options);
    const text = await response.text();
    let data;
    
    try {
        data = JSON.parse(text);
    } catch (e) {
        throw new Error('Server returned invalid JSON: ' + text.substring(0, 200));
    }
    
    if (!response.ok) {
        throw new Error(data.error || 'Something went wrong.');
    }
    
    return data;
}


// =========================================================
// HTML ESCAPING (XSS PREVENTION)
// =========================================================

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}


// =========================================================
// TOAST NOTIFICATION
// =========================================================

function toast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}


// =========================================================
// AUTH NAV (sign in / sign out state in the top nav)
// =========================================================

async function initAuthNav() {
    const authLink = document.getElementById('nav-auth');
    if (!authLink) return;

    try {
        const { user } = await api('me.php');

        if (user) {
            const firstName = (user.full_name || '').split(' ')[0] || 'there';

            if (!document.getElementById('nav-greeting')) {
                authLink.insertAdjacentHTML('beforebegin', `
                    <span id="nav-greeting" class="small muted">Hi, ${escapeHtml(firstName)}</span>
                `);
            }

            authLink.textContent = 'Sign out';
            authLink.href = 'logout.php';
            authLink.onclick = null;
        } else {
            authLink.textContent = 'Sign in';
            authLink.href = 'auth.html';
            authLink.onclick = null;
        }
    } catch (e) {
        // Session check failed silently — nav just keeps its default "Sign in" state.
    }
}

// If this page has a reporter name/email field, prefill it for a signed-in user.
async function prefillReporterFields() {
    const nameField = document.getElementById('reporter_name');
    const emailField = document.getElementById('reporter_email');
    if (!nameField && !emailField) return;

    try {
        const { user } = await api('me.php');
        if (!user) return;
        if (nameField && !nameField.value) nameField.value = user.full_name;
        if (emailField && !emailField.value) emailField.value = user.email;
    } catch (e) {
        // Not signed in or session check failed — leave fields blank for manual entry.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initAuthNav();
    prefillReporterFields();
});

function ticketCardHtml(item) {
    const isLost = item.report_type === 'lost';
    const tagClass = isLost ? 'tag-lost' : 'tag-found';
    const tagLabel = isLost ? '🔍 Lost' : '✅ Found';
    const urgentTag = item.urgent ? '<span class="ticket-tag tag-urgent">Urgent</span>' : '';

    // Found-item photos stay blurred in listings (privacy protection against false claims);
    // lost-item photos (the owner's own property) are shown clearly.
    const photoClearClass = isLost ? 'is-clear' : '';
    const photoHtml = item.photo
        ? `<img src="${escapeHtml(item.photo)}" alt="${escapeHtml(item.title)}" class="ticket-photo ${photoClearClass}">`
        : '';

    return `
        <a class="ticket ${isLost ? 'is-lost' : ''}" href="item-detail.html?id=${item.id}">
            <div class="ticket-stub"></div>
            <div class="ticket-body">
                <div class="ticket-top">
                    <span class="ticket-code">${escapeHtml(item.ticket_code)}</span>
                    <span>
                        <span class="ticket-tag ${tagClass}">${tagLabel}</span>${urgentTag}
                    </span>
                </div>
                <h3 class="ticket-title">${escapeHtml(item.title)}</h3>
                <div class="ticket-meta">
                    <span>📍 ${escapeHtml(item.location_name)}</span>
                    <span>📅 ${escapeHtml(item.event_date)}</span>
                    <span>${escapeHtml(item.category)}</span>
                </div>
                ${photoHtml}
            </div>
        </a>
    `;
}