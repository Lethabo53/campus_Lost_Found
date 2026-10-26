// Campus Lost & Found assistant — floating chat widget (talks to chatbot.php)
(function () {
    if (document.getElementById('lf-chat-launcher')) return;

    var STORE_KEY = 'lf-chat-history-v1';
    var history = [];
    try { history = JSON.parse(sessionStorage.getItem(STORE_KEY) || '[]'); } catch (e) { history = []; }
    function save() { try { sessionStorage.setItem(STORE_KEY, JSON.stringify(history.slice(-40))); } catch (e) {} }

    /* ---------- styles ---------- */
    var css = '\
#lf-chat-launcher{position:fixed;right:20px;bottom:20px;z-index:9998;width:58px;height:58px;border-radius:50%;border:0;cursor:pointer;\
background:var(--sage,#6E7F5C);color:#fff;font-size:26px;box-shadow:0 6px 18px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}\
#lf-chat-launcher:hover{transform:scale(1.07)}\
#lf-chat-launcher:focus-visible,#lf-chat-send:focus-visible,.lf-chip:focus-visible,#lf-chat-close:focus-visible{outline:3px solid var(--gold,#B98A2E);outline-offset:2px}\
#lf-chat{position:fixed;right:20px;bottom:90px;z-index:9999;width:360px;max-width:calc(100vw - 24px);height:520px;max-height:calc(100vh - 110px);\
display:none;flex-direction:column;background:var(--paper-raised,#F6F5EF);color:var(--ink,#2A2B24);border:1px solid var(--line,#C9C6B8);\
border-radius:14px;box-shadow:0 12px 36px rgba(0,0,0,.28);overflow:hidden;font-family:var(--font-body,"Segoe UI",system-ui,sans-serif);font-size:14.5px}\
#lf-chat.open{display:flex}\
#lf-chat-head{background:var(--sage-dark,#566349);color:#fff;padding:12px 14px;display:flex;align-items:center;justify-content:space-between}\
#lf-chat-head strong{font-size:15px}\
#lf-chat-head small{display:block;opacity:.85;font-size:12px}\
#lf-chat-close{background:transparent;border:0;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:2px 6px}\
#lf-chat-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:var(--paper,#EDEBE3)}\
.lf-msg{max-width:86%;padding:9px 12px;border-radius:12px;line-height:1.45;white-space:pre-wrap;word-wrap:break-word}\
.lf-bot{align-self:flex-start;background:#fff;border:1px solid var(--line,#C9C6B8);border-bottom-left-radius:4px}\
.lf-user{align-self:flex-end;background:var(--sage,#6E7F5C);color:#fff;border-bottom-right-radius:4px}\
.lf-link{display:inline-block;margin-top:8px;padding:6px 12px;border-radius:999px;background:var(--clay,#A15A3E);color:#fff!important;text-decoration:none;font-weight:600;font-size:13px}\
.lf-chips{display:flex;flex-wrap:wrap;gap:6px;align-self:flex-start;max-width:96%}\
.lf-chip{border:1px solid var(--sage,#6E7F5C);background:#fff;color:var(--sage-dark,#566349);border-radius:999px;padding:5px 11px;font-size:13px;cursor:pointer;font-family:inherit}\
.lf-chip:hover{background:var(--success-bg,#E4E9DC)}\
.lf-typing{align-self:flex-start;color:var(--ink-soft,#5B5C50);font-size:13px;padding:2px 6px}\
#lf-chat-form{display:flex;gap:8px;padding:10px;border-top:1px solid var(--line,#C9C6B8);background:var(--paper-raised,#F6F5EF)}\
#lf-chat-input{flex:1;padding:9px 12px;border:1px solid var(--line,#C9C6B8);border-radius:999px;font:inherit;background:#fff;color:inherit}\
#lf-chat-send{border:0;border-radius:999px;padding:0 16px;background:var(--sage,#6E7F5C);color:#fff;font-weight:600;cursor:pointer;font-family:inherit}\
#lf-chat-send:disabled{opacity:.6;cursor:default}\
@media (max-width:480px){#lf-chat{right:12px;bottom:84px;height:70vh}#lf-chat-launcher{right:14px;bottom:14px}}';
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    /* ---------- DOM ---------- */
    var launcher = document.createElement('button');
    launcher.id = 'lf-chat-launcher';
    launcher.type = 'button';
    launcher.setAttribute('aria-label', 'Open Lost & Found assistant');
    launcher.textContent = '💬';

    var box = document.createElement('div');
    box.id = 'lf-chat';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Lost and Found assistant');
    box.innerHTML =
        '<div id="lf-chat-head"><div><strong>Lost &amp; Found Assistant</strong><small>Ask me anything about the site</small></div>' +
        '<button id="lf-chat-close" type="button" aria-label="Close chat">&times;</button></div>' +
        '<div id="lf-chat-body" aria-live="polite"></div>' +
        '<form id="lf-chat-form" autocomplete="off"><input id="lf-chat-input" type="text" maxlength="300" placeholder="Type your question…" aria-label="Your message">' +
        '<button id="lf-chat-send" type="submit">Send</button></form>';

    document.body.appendChild(launcher);
    document.body.appendChild(box);

    var body = document.getElementById('lf-chat-body');
    var form = document.getElementById('lf-chat-form');
    var input = document.getElementById('lf-chat-input');
    var sendBtn = document.getElementById('lf-chat-send');

    function scrollDown() { body.scrollTop = body.scrollHeight; }

    function addMessage(text, who, link) {
        var el = document.createElement('div');
        el.className = 'lf-msg ' + (who === 'user' ? 'lf-user' : 'lf-bot');
        el.textContent = text;
        if (link && link.url) {
            el.appendChild(document.createElement('br'));
            var a = document.createElement('a');
            a.className = 'lf-link';
            a.href = link.url;
            a.textContent = link.label || 'Open';
            el.appendChild(a);
        }
        body.appendChild(el);
        scrollDown();
    }

    function clearChips() {
        var old = body.querySelectorAll('.lf-chips');
        for (var i = 0; i < old.length; i++) old[i].remove();
    }

    function addChips(chips) {
        clearChips();
        if (!chips || !chips.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'lf-chips';
        chips.forEach(function (label) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'lf-chip';
            b.textContent = label;
            b.addEventListener('click', function () { send(label); });
            wrap.appendChild(b);
        });
        body.appendChild(wrap);
        scrollDown();
    }

    function showTyping() {
        var t = document.createElement('div');
        t.className = 'lf-typing';
        t.id = 'lf-typing';
        t.textContent = 'Assistant is typing…';
        body.appendChild(t);
        scrollDown();
    }
    function hideTyping() { var t = document.getElementById('lf-typing'); if (t) t.remove(); }

    function botSay(reply, chips, link, remember) {
        addMessage(reply, 'bot', link);
        addChips(chips);
        if (remember !== false) { history.push({ who: 'bot', text: reply, link: link || null, chips: chips || [] }); save(); }
    }

    async function send(text) {
        text = (text || '').trim();
        if (!text) return;
        clearChips();
        addMessage(text, 'user');
        history.push({ who: 'user', text: text });
        save();
        input.value = '';
        sendBtn.disabled = true;
        showTyping();
        try {
            var res = await fetch('chatbot.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            var data = await res.json();
            hideTyping();
            if (res.status === 401) {
                botSay('Your session has expired. Please sign in again.', [], { label: 'Sign in', url: 'auth.html' });
            } else if (!res.ok) {
                botSay(data.error || 'Something went wrong. Please try again.', []);
            } else {
                botSay(data.reply, data.chips, data.link);
            }
        } catch (err) {
            hideTyping();
            botSay("I couldn't reach the server. Please check that XAMPP (Apache and MySQL) is running and try again.", []);
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    }

    /* ---------- open / close ---------- */
    var started = false;
    function openChat() {
        box.classList.add('open');
        launcher.setAttribute('aria-expanded', 'true');
        if (!started) {
            started = true;
            if (history.length) {
                history.forEach(function (m, i) {
                    addMessage(m.text, m.who, m.link);
                    if (i === history.length - 1 && m.who === 'bot') addChips(m.chips);
                });
            } else {
                botSay("Hi! 👋 I'm the Campus Lost & Found assistant. I can explain how to report an item, claim one, track a ticket or earn rewards. What would you like to do?",
                    ['Report a lost item', 'Report a found item', 'Check my ticket', 'Rewards & snacks', 'How do I claim an item?']);
            }
        }
        input.focus();
        try { sessionStorage.setItem('lf-chat-open', '1'); } catch (e) {}
    }
    function closeChat() {
        box.classList.remove('open');
        launcher.setAttribute('aria-expanded', 'false');
        launcher.focus();
        try { sessionStorage.removeItem('lf-chat-open'); } catch (e) {}
    }

    launcher.addEventListener('click', function () { box.classList.contains('open') ? closeChat() : openChat(); });
    document.getElementById('lf-chat-close').addEventListener('click', closeChat);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.classList.contains('open')) closeChat(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });

    try { if (sessionStorage.getItem('lf-chat-open') === '1') openChat(); } catch (e) {}
})();