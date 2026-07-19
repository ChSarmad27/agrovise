<?php
/**
 * AGROVISE - Messaging Centre (admin-only)
 * Authorize the company phone number the system sends from, and dispatch the
 * messages the system has composed (invoice notices to officers/clients,
 * payment confirmations to vendors). Each message opens in WhatsApp from the
 * authorized number with the text prefilled, then is marked as sent.
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
requirePermission('employees');   // admin-only, same gate as designations

$conn = getDBConnection();
$errors = [];

// Save settings (the "authorize this number" button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $number = trim($_POST['messaging_number'] ?? '');
    $enabled = isset($_POST['messaging_enabled']) ? '1' : '0';
    if ($enabled === '1' && !preg_match('/^0\d{10}$|^\+?\d{10,15}$/', preg_replace('/[\s-]/', '', $number))) {
        $errors[] = 'Enter a valid phone number (e.g. 03001234567) before activating messaging.';
    } else {
        setSetting($conn, 'messaging_number', $number);
        setSetting($conn, 'messaging_enabled', $enabled);
        // Android SIM gateway connection (Traccar SMS Gateway or SMS Gate app)
        setSetting($conn, 'sms_gateway_type', ($_POST['sms_gateway_type'] ?? 'traccar') === 'smsgate' ? 'smsgate' : 'traccar');
        setSetting($conn, 'sms_gateway_url', trim($_POST['sms_gateway_url'] ?? ''));
        setSetting($conn, 'sms_gateway_user', trim($_POST['sms_gateway_user'] ?? ''));
        if (trim($_POST['sms_gateway_pass'] ?? '') !== '') {
            setSetting($conn, 'sms_gateway_pass', trim($_POST['sms_gateway_pass']));
        }
        setSetting($conn, 'sms_auto_send', isset($_POST['sms_auto_send']) ? '1' : '0');
        setFlashMessage('success', $enabled === '1'
            ? 'Messaging activated — the system now composes messages from ' . sanitize($number) . ' on every invoice and vendor payment.'
            : 'Messaging settings saved (sending is currently off).');
        header('Location: messaging.php');
        exit;
    }
}

// Test SMS through the Android gateway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sms'])) {
    $to = trim($_POST['test_number'] ?? '');
    if ($to === '') {
        $errors[] = 'Enter a phone number to send the test SMS to.';
    } else {
        list($ok, $info) = sendSmsViaGateway($conn, $to, "AGROVISE test message — your SIM gateway is connected and working." . messageSignature());
        setFlashMessage($ok ? 'success' : 'error', $ok
            ? 'Test SMS accepted by the gateway (' . sanitize($info) . ') — check the phone at ' . sanitize($to) . '.'
            : 'Test SMS failed: ' . sanitize($info));
        header('Location: messaging.php');
        exit;
    }
}

// Send one queued message as a real SMS through the gateway
if (isset($_GET['sms']) && is_numeric($_GET['sms'])) {
    $st = $conn->prepare("SELECT * FROM outbound_messages WHERE id = ? AND status = 'PENDING'");
    $st->execute([intval($_GET['sms'])]);
    if ($m = $st->fetch()) {
        list($ok, $info) = sendSmsViaGateway($conn, $m['phone'], $m['body']);
        if ($ok) {
            $conn->prepare("UPDATE outbound_messages SET status = 'SENT', sent_via = 'SMS', sent_at = NOW() WHERE id = ?")->execute([$m['id']]);
            setFlashMessage('success', 'SMS to ' . sanitize($m['recipient_name']) . ' accepted by the gateway.');
        } else {
            setFlashMessage('error', 'SMS failed: ' . sanitize($info) . ' — the message stays pending.');
        }
    }
    header('Location: messaging.php');
    exit;
}

// Mark sent / delete
if (isset($_GET['sent']) && is_numeric($_GET['sent'])) {
    $conn->prepare("UPDATE outbound_messages SET status = 'SENT', sent_via = 'WHATSAPP', sent_at = NOW() WHERE id = ?")->execute([intval($_GET['sent'])]);
    setFlashMessage('success', 'Message marked as sent.');
    header('Location: messaging.php');
    exit;
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->prepare("DELETE FROM outbound_messages WHERE id = ?")->execute([intval($_GET['delete'])]);
    setFlashMessage('success', 'Message removed from the queue.');
    header('Location: messaging.php');
    exit;
}

$msgNumber  = getSetting($conn, 'messaging_number') ?? '';
$msgEnabled = getSetting($conn, 'messaging_enabled') === '1';
$gwType     = getSetting($conn, 'sms_gateway_type') === 'smsgate' ? 'smsgate' : 'traccar';
$gwUrl      = getSetting($conn, 'sms_gateway_url') ?? '';
$gwUser     = getSetting($conn, 'sms_gateway_user') ?? '';
$gwPassSet  = trim((string)getSetting($conn, 'sms_gateway_pass')) !== '';
$gwReady    = smsGatewayConfigured($conn);
$autoSend   = getSetting($conn, 'sms_auto_send') === '1';
$pending = $conn->query("SELECT * FROM outbound_messages WHERE status = 'PENDING' ORDER BY created_at DESC")->fetchAll();
$sent = $conn->query("SELECT * FROM outbound_messages WHERE status = 'SENT' ORDER BY sent_at DESC LIMIT 20")->fetchAll();

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messaging - AGROVISE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=ed3">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header"><h1><i class="fas fa-comment-sms"></i> Messaging Centre</h1></div>

            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><ul style="margin:0; padding-left:20px;"><?php foreach ($errors as $e) echo "<li>" . sanitize($e) . "</li>"; ?></ul></div>
            <?php endif; ?>

            <!-- Sender number authorization -->
            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-phone"></i> System Sender Number</h2></div>
                <div style="padding:20px;">
                    <form method="POST" style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
                        <?php echo csrfField(); ?>
                        <div class="form-group" style="margin:0; min-width:260px;">
                            <label class="form-label">Company Phone Number (messages are sent from this number)</label>
                            <input type="text" name="messaging_number" class="form-input" placeholder="e.g. 03001234567" value="<?php echo sanitize($msgNumber); ?>">
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; padding-bottom:10px; cursor:pointer;">
                            <input type="checkbox" name="messaging_enabled" <?php echo $msgEnabled ? 'checked' : ''; ?>> Messaging active
                        </label>

                        <div style="flex-basis:100%; border-top:1px dashed #d0d6cd; padding-top:16px; margin-top:4px;">
                            <p style="font-weight:600; margin-bottom:10px;"><i class="fas fa-sim-card" style="color:var(--primary-green);"></i> SIM Network Gateway (Android phone app)</p>
                            <div style="display:grid; grid-template-columns:1fr 1.4fr 1fr 1fr; gap:14px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Gateway App</label>
                                    <select name="sms_gateway_type" id="gwType" class="form-select" onchange="gwTypeSync()">
                                        <option value="traccar" <?php echo $gwType === 'traccar' ? 'selected' : ''; ?>>Traccar SMS Gateway</option>
                                        <option value="smsgate" <?php echo $gwType === 'smsgate' ? 'selected' : ''; ?>>SMS Gate (sms-gate.app)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Gateway URL</label>
                                    <input type="text" name="sms_gateway_url" class="form-input" id="gwUrlInput" placeholder="http://192.168.1.50:8082" value="<?php echo sanitize($gwUrl); ?>">
                                </div>
                                <div class="form-group" style="margin:0;" id="gwUserWrap">
                                    <label class="form-label">Gateway Username</label>
                                    <input type="text" name="sms_gateway_user" class="form-input" placeholder="from the app" value="<?php echo sanitize($gwUser); ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label"><span id="gwPassLabel">API Key</span> <?php echo $gwPassSet ? '<small style="color:#2e7d32;">(saved — leave blank to keep)</small>' : ''; ?></label>
                                    <input type="password" name="sms_gateway_pass" class="form-input" autocomplete="new-password" placeholder="<?php echo $gwPassSet ? '••••••••' : 'from the app'; ?>">
                                </div>
                            </div>
                            <label style="display:flex; align-items:center; gap:8px; margin-top:12px; cursor:pointer;">
                                <input type="checkbox" name="sms_auto_send" <?php echo $autoSend ? 'checked' : ''; ?>>
                                <strong>Auto-send by SMS</strong>&nbsp;— every new invoice / vendor-payment message goes out over the SIM network immediately, no clicks needed
                            </label>
                        </div>

                        <button type="submit" name="save_settings" value="1" class="btn btn-primary" style="margin-bottom:2px;"><i class="fas fa-key"></i> Authorize &amp; Save</button>
                    </form>

                    <?php if ($gwReady): ?>
                    <form method="POST" style="display:flex; gap:10px; align-items:center; margin-top:16px; border-top:1px dashed #d0d6cd; padding-top:14px;">
                        <?php echo csrfField(); ?>
                        <label class="form-label" style="margin:0;">Test the gateway:</label>
                        <input type="text" name="test_number" class="form-input" placeholder="0300-1234567" style="max-width:200px;">
                        <button type="submit" name="test_sms" value="1" class="btn btn-secondary btn-sm"><i class="fas fa-vial"></i> Send Test SMS</button>
                        <small style="color:#b3261e;"><i class="fas fa-triangle-exclamation"></i> The test uses the last <strong>saved</strong> settings — click "Authorize &amp; Save" first if you changed anything above.</small>
                    </form>
                    <?php endif; ?>

                    <p style="font-size:0.8rem; color:#777; margin-top:12px;">
                        <i class="fas fa-info-circle"></i> When active, the system composes a message automatically on every invoice
                        (to the sales officer and the client) and on every vendor payment.
                        With the SIM gateway connected<?php echo $autoSend ? ' and auto-send ON, messages leave immediately as real SMS' : ', use the SMS button on each pending message (or WhatsApp as a fallback)'; ?>.
                        Status: <strong style="color:<?php echo $msgEnabled ? '#2e7d32' : '#b3261e'; ?>;"><?php echo $msgEnabled ? 'ACTIVE' : 'OFF'; ?></strong>
                        &middot; Gateway: <strong style="color:<?php echo $gwReady ? '#2e7d32' : '#b3261e'; ?>;"><?php echo $gwReady ? 'CONFIGURED' : 'NOT SET'; ?></strong>
                        &middot; Auto-send: <strong style="color:<?php echo $autoSend ? '#2e7d32' : '#b3261e'; ?>;"><?php echo $autoSend ? 'ON' : 'OFF'; ?></strong>
                    </p>
                </div>
            </div>

            <!-- Pending queue -->
            <div class="data-card" style="margin-bottom:20px;">
                <div class="data-card-header"><h2><i class="fas fa-paper-plane"></i> Pending Messages (<?php echo count($pending); ?>)</h2></div>
                <div style="padding:20px;">
                    <?php if (empty($pending)): ?>
                        <p style="color:#8a8f83; text-align:center; padding:14px 0;">No pending messages — new ones appear here when invoices or vendor payments are logged.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Queued</th><th>To</th><th>Phone</th><th>Message</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($pending as $m): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo date('d M, h:i A', strtotime($m['created_at'])); ?></td>
                                <td><strong><?php echo sanitize($m['recipient_name']); ?></strong><br><small style="color:#888;"><?php echo sanitize($m['recipient_type']); ?><?php echo $m['context'] ? ' · ' . sanitize($m['context']) : ''; ?></small></td>
                                <td style="white-space:nowrap;"><?php echo sanitize($m['phone']); ?></td>
                                <td style="max-width:420px;"><div style="white-space:pre-wrap; font-size:0.82rem; background:#f7f9f6; border:1px solid #e0e6dd; border-radius:8px; padding:10px;"><?php echo sanitize($m['body']); ?></div></td>
                                <td>
                                    <div class="action-btns" style="white-space:nowrap;">
                                        <?php if ($gwReady): ?>
                                        <a href="?sms=<?php echo $m['id']; ?>" class="btn btn-primary btn-sm" title="Send as a real SMS through the SIM gateway"><i class="fas fa-tower-cell"></i> SMS</a>
                                        <?php endif; ?>
                                        <a href="<?php echo sanitize(waSendLink($m['phone'], $m['body'])); ?>" target="_blank" class="btn btn-secondary btn-sm" title="Open in WhatsApp with the message prefilled"><i class="fab fa-whatsapp"></i></a>
                                        <a href="?sent=<?php echo $m['id']; ?>" class="btn-icon edit" title="Mark as sent"><i class="fas fa-check"></i></a>
                                        <a href="?delete=<?php echo $m['id']; ?>" class="btn-icon delete" title="Discard" onclick="return confirm('Discard this message?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recently sent -->
            <div class="data-card">
                <div class="data-card-header"><h2><i class="fas fa-check-double"></i> Recently Sent</h2></div>
                <div style="padding:20px;">
                    <?php if (empty($sent)): ?>
                        <p style="color:#8a8f83; text-align:center; padding:14px 0;">Nothing sent yet.</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead><tr><th>Sent</th><th>To</th><th>Phone</th><th>Channel</th><th>Context</th></tr></thead>
                        <tbody>
                            <?php foreach ($sent as $m): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo $m['sent_at'] ? date('d M, h:i A', strtotime($m['sent_at'])) : '—'; ?></td>
                                <td><strong><?php echo sanitize($m['recipient_name']); ?></strong> <small style="color:#888;">(<?php echo sanitize($m['recipient_type']); ?>)</small></td>
                                <td><?php echo sanitize($m['phone']); ?></td>
                                <td>
                                    <?php if (($m['sent_via'] ?? '') === 'SMS'): ?>
                                        <span class="badge" style="background:#e8f5e9; color:#2e7d32;"><i class="fas fa-tower-cell"></i> SMS</span>
                                    <?php elseif (($m['sent_via'] ?? '') === 'WHATSAPP'): ?>
                                        <span class="badge" style="background:#e3f2fd; color:#1565c0;"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#eee; color:#666;">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize($m['context'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Traccar authenticates with a single API key; SMS Gate uses username+password.
    function gwTypeSync() {
        var type = document.getElementById('gwType').value;
        document.getElementById('gwUserWrap').style.display = type === 'traccar' ? 'none' : 'block';
        document.getElementById('gwPassLabel').textContent = type === 'traccar' ? 'API Key' : 'Gateway Password';
        document.getElementById('gwUrlInput').placeholder = type === 'traccar' ? 'http://192.168.1.50:8082' : 'http://192.168.1.50:8080';
    }
    gwTypeSync();
    </script>
</body>
</html>
