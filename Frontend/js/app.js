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

            // Admins get an extra link to the admin console
            if (user.role === 'admin' && !document.getElementById('nav-admin')) {
                const here = location.pathname.endsWith('admin-console.html');
                authLink.insertAdjacentHTML('beforebegin',
                    '<a href="admin-console.html" id="nav-admin"' + (here ? ' class="active"' : '') + '>Admin console</a>');
            }
            // Students get a Messages link (to write to the admin) with an unread badge
            if (user.role !== 'admin' && !document.getElementById('nav-messages')) {
                const here = location.pathname.endsWith('messages.html');
                authLink.insertAdjacentHTML('beforebegin',
                    '<a href="messages.html" id="nav-messages"' + (here ? ' class="active"' : '') + '>Messages <span id="nav-msg-badge" class="msg-badge" style="display:none;"></span></a>');
                if (!document.getElementById('msg-badge-style')) {
                    document.head.insertAdjacentHTML('beforeend',
                        '<style id="msg-badge-style">.msg-badge{display:inline-block;min-width:18px;padding:0 6px;border-radius:999px;background:#c0562f;color:#fff;font-size:.72rem;line-height:18px;text-align:center;margin-left:4px}</style>');
                }
                refreshBadge();
            }
        } else {
            authLink.textContent = 'Sign in';
            authLink.href = 'auth.html';
            authLink.onclick = null;
        }
    } catch (e) {
        // Session check failed silently — nav just keeps its default "Sign in" state.
    }
}

// Unread admin replies shown on the Messages nav link
async function refreshBadge() {
    const el = document.getElementById('nav-msg-badge');
    if (!el) return;
    try {
        const { unread } = await api('messages.php?action=unread');
        el.textContent = unread;
        el.style.display = unread ? 'inline-block' : 'none';
    } catch (e) { /* ignore */ }
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

    // Reports the admin has not approved yet (only the reporter and the admin can see these)
    let approvalTag = '';
    if (item.approval_status === 'pending') approvalTag = '<span class="ticket-tag tag-urgent">Waiting for admin approval</span>';
    if (item.approval_status === 'rejected') approvalTag = '<span class="ticket-tag tag-urgent">Rejected</span>';

    // Found items show how many hours are left in the 30-day claim window
    let countdownHtml = '';
    if (!isLost && (item.status === 'open' || item.status === 'matched') && item.seconds_left !== undefined) {
        countdownHtml = item.seconds_left > 0
            ? `<span>⏳ ${item.hours_left}h left to claim</span>`
            : '<span>⏳ Claim window ended</span>';
    }

    return `
        <a class="ticket ${isLost ? 'is-lost' : ''}" href="item-detail.html?id=${item.id}">
            <div class="ticket-stub"></div>
            <div class="ticket-body">
                <div class="ticket-top">
                    <span class="ticket-code">${escapeHtml(item.ticket_code)}</span>
                    <span>
                        <span class="ticket-tag ${tagClass}">${tagLabel}</span>${urgentTag}${approvalTag}
                    </span>
                </div>
                <h3 class="ticket-title">${escapeHtml(item.title)}</h3>
                <div class="ticket-meta">
                    <span>📍 ${escapeHtml(item.location_name)}</span>
                    <span>📅 ${escapeHtml(item.event_date)}</span>
                    <span>${escapeHtml(item.category)}</span>
                    ${countdownHtml}
                </div>
                ${photoHtml}
            </div>
        </a>
    `;
}