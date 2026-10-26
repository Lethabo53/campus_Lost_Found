<?php
// Campus Lost & Found assistant — rule-based chatbot (no external AI service, no API key).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$user = require_login($pdo);

$data    = request_json();
$message = mb_substr(clean($data['message'] ?? ''), 0, 300);

if ($message === '') {
    json_response(['reply' => "Type a question and I'll do my best to help.", 'chips' => []]);
}

// Normalise: lower-case, punctuation -> spaces, pad with spaces
$text = ' ' . trim(preg_replace('/\s+/', ' ', preg_replace("/[^a-z0-9' ]+/u", ' ', mb_strtolower($message)))) . ' ';

$MENU = ['Report a lost item', 'Report a found item', 'Check my ticket', 'Rewards & snacks', 'How do I claim an item?'];

function reply(string $reply, array $chips = [], ?array $link = null): never {
    json_response(['reply' => $reply, 'chips' => $chips, 'link' => $link]);
}

// Keyword match. A keyword starting with "=" must match as a whole word; otherwise it must start at a word boundary
// (so "credit" matches "credits", but "date" does not match "update").
function kw_match(string $text, string $kw): bool {
    $whole = $kw[0] === '=';
    $kw = ltrim($kw, '=');
    $pattern = '/(?<![a-z0-9])' . preg_quote($kw, '/') . ($whole ? '(?![a-z0-9])' : '') . '/u';
    return (bool) preg_match($pattern, $text);
}

function has(string $text, array $words): bool {
    foreach ($words as $w) {
        if (kw_match($text, $w)) return true;
    }
    return false;
}

$STATUS = [
    'open'     => 'Open — still waiting to be matched or claimed.',
    'matched'  => 'Matched — a possible match has been found. Open the item page to see it.',
    'claimed'  => 'Claimed — the owner has passed verification and the item is being handed over.',
    'reunited' => 'Reunited — the item is back with its owner. 🎉',
    'donated'  => 'Donated — it was unclaimed after the waiting period and went to charity.',
    'closed'   => 'Closed — this report is finished.',
];

/* ---------- 1. Ticket lookup (e.g. LF-1A2B3C4D) ---------- */
if (preg_match('/\bLF-[A-Z0-9]{8}\b/i', $message, $m)) {
    $code = strtoupper($m[0]);
    $stmt = $pdo->prepare("SELECT id, ticket_code, report_type, title, status, approval_status, rejection_reason, event_date, location_name, reporter_email
                           FROM items WHERE ticket_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $item = $stmt->fetch();

    if (!$item) {
        reply("I couldn't find a ticket called $code. Please check the code and try again.", ['Check my ticket']);
    }
    if (strtolower($item['reporter_email']) !== strtolower($user['email'])) {
        reply("I can only show tickets you reported yourself. You can still look for items on the Browse page.",
              [], ['label' => 'Open Browse', 'url' => 'browse.html']);
    }
    $type = $item['report_type'] === 'lost' ? 'lost' : 'found';
    if ($item['approval_status'] === 'pending') {
        $statusText = 'Waiting for admin approval — it will appear on Browse for everyone once the admin approves it.';
    } elseif ($item['approval_status'] === 'rejected') {
        $statusText = 'Not approved by the admin. Reason: ' . ($item['rejection_reason'] ?: 'it did not meet the reporting guidelines.');
    } else {
        $statusText = $STATUS[$item['status']] ?? $item['status'];
    }
    reply("Ticket {$item['ticket_code']} is your $type report \"{$item['title']}\" (" . $item['event_date']
        . ", " . $item['location_name'] . ").\n\nStatus: " . $statusText,
        [], ['label' => 'View item page', 'url' => 'item-detail.html?id=' . (int) $item['id']]);
}

/* ---------- 2. Personal data questions ---------- */
if (has($text, ['my points', 'my credits', 'my balance', 'how many points', 'how many credits', 'snack credits do i', 'my snack'])) {
    $stmt = $pdo->prepare("SELECT points, snack_credits FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $u = $stmt->fetch();
    reply("You currently have {$u['points']} points and {$u['snack_credits']} snack credits. You can redeem credits (minimum 5) on the My Rewards page.",
          ['How do I earn credits?'], ['label' => 'Open My Rewards', 'url' => 'rewards.html']);
}

if (has($text, ['my reports', 'my items', 'my tickets', 'my lost', 'what have i reported', 'my report'])) {
    $stmt = $pdo->prepare("SELECT id, ticket_code, report_type, title, status FROM items
                           WHERE LOWER(reporter_email) = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([strtolower($user['email'])]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        reply("You haven't reported anything yet. Would you like to report something?", ['Report a lost item', 'Report a found item']);
    }
    $lines = array_map(fn($r) => "• {$r['ticket_code']} — {$r['report_type']}: {$r['title']} ({$r['status']})", $rows);
    reply("Your latest reports:\n" . implode("\n", $lines) . "\n\nSend me a ticket code to see its full status.");
}

/* ---------- 3. Knowledge base ---------- */
$KB = [
    'greeting' => [
        'k' => ['=hello', '=hi', '=hey', 'good morning', 'good afternoon', 'good evening', '=howzit', '=sawubona', '=molo'],
        'r' => "Hi " . explode(' ', $user['full_name'])[0] . "! 👋 I'm the Campus Lost & Found assistant. What do you need help with?",
        'c' => $MENU,
    ],
    'thanks' => [
        'k' => ['thank', '=cheers', 'appreciate'],
        'r' => "You're welcome! Anything else I can help with?",
    ],
    'bye' => [
        'k' => ['=bye', 'goodbye', 'see you', '=cya'],
        'r' => "Goodbye! Good luck finding your things. 👋",
    ],
    'report_lost' => [
        'k' => ['report lost', 'lost something', 'i lost', 'lost my', 'lost item', 'missing', 'misplaced', 'cant find my', "can't find my", 'report a lost'],
        'r' => "To report a lost item:\n1. Open Report Lost.\n2. Describe the item (title, category, colour, brand).\n3. Choose today's date or an earlier date — future dates aren't allowed.\n4. Drop a pin on the campus map where you lost it.\n5. Add up to 5 photos if you have them.\n\nThe report goes to the campus admin first. Once the admin approves it, it appears on Browse and we automatically look for matches against found items.",
        'c' => ['Why is my report pending?', 'Report a found item'],
        'l' => ['label' => 'Report a lost item', 'url' => 'report-lost.html'],
    ],
    'report_found' => [
        'k' => ['report found', 'i found', 'found something', 'found an item', 'found a', 'handed in', 'hand in', 'report a found', 'found item'],
        'r' => "Thank you for helping! To report a found item:\n1. Open Report Found and describe it.\n2. Pick where and when you found it.\n3. Add a photo.\n4. Set TWO security questions only the owner could answer (for example, \"What's the wallpaper on the phone?\").\n\nThe report goes to the campus admin first. When the admin approves it you earn 10 points and 5 snack credits. If nobody claims the item within 30 days (720 hours) it is flagged for donation.",
        'c' => ['How do I earn credits?', 'Report a lost item'],
        'l' => ['label' => 'Report a found item', 'url' => 'report-found.html'],
    ],
    'claim' => [
        'k' => ['claim', 'is this mine', 'my item', 'collect', 'get my item', 'get it back', 'how do i get', 'retrieve', 'verify'],
        'r' => "To claim an item that someone found:\n1. Go to Browse and open the item that looks like yours.\n2. Answer the two security questions set by the finder.\n3. If both answers match, your claim is sent to the campus admin, who contacts you to arrange the hand-over.\n\nThe finder's details are never shown to you — communication always starts with the admin.",
        'c' => ['I got my item back', 'What if I fail verification?'],
        'l' => ['label' => 'Browse items', 'url' => 'browse.html'],
    ],
    'fail_verify' => [
        'k' => ['fail verification', 'wrong answer', 'answers wrong', 'rejected', 'not verified', 'verification failed', 'cant answer', "can't answer"],
        'r' => "If your answers don't match, the claim is rejected. Double-check the details of the item (colour, contents, marks) and try again. If you're sure the item is yours, visit the campus lost-property counter with proof of ownership.",
    ],
    'own_found' => [
        'k' => ['got my item back', 'got it back', 'i found my', 'found my item', 'i have my', 'recovered', 'close my report', 'close report', 'no longer lost', 'item back'],
        'r' => "Great news! Open your lost report on the Browse page (or use \"My reports\" below) and press the \"I found my item\" button. That closes your report. Note: you can't log your own lost item as a found item, and no reward points are given for it.",
        'c' => ['My reports'],
        'l' => ['label' => 'Browse items', 'url' => 'browse.html'],
    ],
    'after_report' => [
        'k' => ['what happens after', 'after i report', 'how does matching', 'matching', 'matches', 'how do you match', 'notify', 'notification'],
        'r' => "After you report, the system compares your item with others using the title, description, colour, category, location and photo. The best possible matches (with a % score) appear on your item page. Check back there — new found reports are matched against open lost reports automatically.",
        'c' => ['Check my ticket', 'My reports'],
    ],
    'ticket' => [
        'k' => ['ticket', 'check status', 'status of', 'track', 'progress', 'ticket code'],
        'r' => "Send me your ticket code (it looks like LF-1A2B3C4D) and I'll tell you its status. You can also say \"my reports\" to see your latest ones.",
        'c' => ['My reports'],
    ],
    'rewards' => [
        'k' => ['reward', 'earn', 'snack', 'credit', 'points', 'redeem', 'voucher', 'good samaritan', 'prize'],
        'r' => "Rewards work like this:\n• Report a found item: +10 points and +5 snack credits, given when the admin approves your report.\n• When the admin confirms it was reunited with its owner: a bonus of +15 points and +10 snack credits.\n• Redeem at least 5 credits on the My Rewards page to get a code (like SN-1A2B3C4D). Show that code at the campus snack venue to claim your snack.\n\nReporting your own lost item earns nothing.",
        'c' => ['My points', 'Report a found item'],
        'l' => ['label' => 'Open My Rewards', 'url' => 'rewards.html'],
    ],
    'forgot_password' => [
        'k' => ['forgot', 'reset password', 'reset my password', 'password reset', "can't log in", 'cant log in', 'cannot log in', 'cant sign in', "can't sign in", 'lost password', 'incorrect password', 'change password', 'new password'],
        'r' => "To reset your password:\n1. Go to the sign-in page and press \"Forgot your password?\".\n2. Enter your student email.\n3. Open the reset link shown on screen (it's valid for 30 minutes).\n4. Choose a new password of at least 8 characters.",
        'c' => [],
    ],
    'signup' => [
        'k' => ['sign up', 'signup', 'register', 'create account', 'new account', 'student email', 'which email', 'what email', 'email format', 'student number'],
        'r' => "To create an account use your student email: your student number (starting with 40, 9 digits in total) followed by @my.richfield.ac.za — for example 402311567@my.richfield.ac.za. Enter your first name AND surname, and choose a password of at least 8 characters.",
    ],
    'signout' => [
        'k' => ['sign out', 'signout', 'log out', 'logout', 'log off'],
        'r' => "Use the \"Sign out\" link at the far right of the top menu on any page.",
    ],
    'date_rule' => [
        'k' => ['future date', '=date', '=dates', 'calendar', 'when did', 'yesterday'],
        'r' => "When reporting, you can choose today's date or any earlier date. Future dates are blocked because an item can't have been lost or found in the future.",
    ],
    'photos' => [
        'k' => ['photo', 'picture', 'image', 'upload'],
        'r' => "Lost reports accept up to 5 photos; found reports accept 1 photo. JPG, PNG, GIF and WebP images work. Clear photos help the system match items — and finders' photos are kept blurred until an owner passes verification.",
    ],
    'donation' => [
        'k' => ['donate', 'donation', 'charity', 'unclaimed', 'what happens to unclaimed'],
        'r' => "Found items have a 30-day (720-hour) claim window, counted from the day they were reported. Open any found item to see the live hours-left countdown. Items nobody claims in that time are flagged for donation to charity. Only the admin can send items to charity, and you can see them on the Donations page.",
        'l' => ['label' => 'Open Donations', 'url' => 'donation.html'],
    ],
    'security_questions' => [
        'k' => ['security question', 'secret question', 'what questions', 'what should i ask'],
        'r' => "When you report a found item you set two questions only the true owner could answer — something not visible in the photo, like the phone's wallpaper, a keyring detail, or what's inside a bag. Avoid questions anyone could guess.",
    ],
    'about' => [
        'k' => ['what is this', 'about this', 'who made', 'who built', 'tech titans', 'what can you do', 'help', 'what do you do'],
        'r' => "I'm the Campus Lost & Found assistant for Richfield Centurion Campus. I can explain how to report lost or found items, claim an item, track a ticket, and how rewards work. Pick a topic below or just type your question.",
        'c' => $MENU,
    ],
    'approval' => [
        'k' => ['approve', 'approval', 'pending', 'waiting for admin', 'rejected', 'not showing', "can't see my", 'cant see my', 'not on browse', 'why is my report', 'admin review', 'admin first'],
        'r' => "Every report is reviewed by the campus admin before it is shown to everyone. Until then it shows as \"Waiting for admin approval\" and only you (and the admin) can see it, under Browse → My reports. After approval it appears on Browse, matching starts, and found-item rewards are paid. If it is rejected you will see the reason on the item page and can submit a new report.",
        'c' => ['My reports', 'Check my ticket'],
    ],
    'contact' => [
        'k' => ['contact', 'speak to', 'talk to', 'human', 'staff', 'admin', 'support', 'security office', 'lost property'],
        'r' => "For anything I can't help with, visit the campus lost-property counter or speak to student affairs staff. Staff manage reunions, donations and snack redemptions.",
    ],
];

$best = null;
$bestScore = 0;
foreach ($KB as $key => $entry) {
    $score = 0;
    foreach ($entry['k'] as $kw) {
        if (kw_match($text, $kw)) {
            $score += str_contains(trim($kw), ' ') ? 2 : 1;   // phrases count more than single words
        }
    }
    if ($score > $bestScore) {
        $bestScore = $score;
        $best = $entry;
    }
}

if ($best !== null) {
    reply($best['r'], $best['c'] ?? [], $best['l'] ?? null);
}

reply("Sorry, I didn't quite get that. I can help with reporting lost or found items, claiming an item, ticket status, rewards, and account questions. Try one of these:", $MENU);