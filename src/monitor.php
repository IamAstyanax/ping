<?php
require 'vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// -------------------
// CONFIGURATION
// PING
// A stupid simple cloud native PHP application to monitor a web endpoint
// Written by IamAstyanax - Benjamin Ritter 
// With some help from AI and Stack Overflow :)
// I am not a developer, but I play PHP developer on TV
// 
// -------------------
$sitesJson = getenv('SITES_JSON');
if (!$sitesJson) {
    echo "Error: SITES_JSON environment variable not set.\n";
    exit(1);
}
$sites = json_decode($sitesJson, true);
if (!is_array($sites)) {
    echo "Error: SITES_JSON is not valid JSON or not an array.\n";
    exit(1);
}

$checkInterval = (int) getenv('CHECK_INTERVAL') ?: 5;
$maxAttempts   = (int) getenv('MAX_ATTEMPTS') ?: 5;
$cycleDelay    = (int) getenv('CYCLE_DELAY') ?: 60;

// O365 Email Config
$email_user   = getenv('EMAIL_USER');
$email_pass  = getenv('EMAIL_PASS');
$alertEmail = getenv('ALERT_EMAIL');

$statusFile = '/app/status.json';

// Load status if exists, else initialize empty array for all sites
if (file_exists($statusFile)) {
    $allStatusData = json_decode(file_get_contents($statusFile), true);
} else {
    $allStatusData = [];
}

// Initialize site-specific status if missing, preserving your fields and defaults
foreach ($sites as $site) {
    $target = $site['target'];
    if (!isset($allStatusData[$target])) {
        $allStatusData[$target] = [
            'uptimeCycles' => 0,
            'lastStatus' => 'UNKNOWN',
            'lastChecked' => null,
            'totalChecks' => 0,
            'successfulChecks' => 0,
            'serviceStartedAt' => date('Y-m-d H:i:s'),
        ];
    }
}

// -------------------
// PING FUNCTION
// -------------------
function isHostUp($host) {
    $pingResult = shell_exec("ping -c 1 -W 2 " . escapeshellarg($host));
    return (strpos($pingResult, "1 packets transmitted, 1 received") !== false);
}

// -------------------
// WEB (HTTP) CHECK FUNCTION
// -------------------
function isSiteUp($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Consider 200-399 as UP
    return ($httpCode >= 200 && $httpCode < 400);
}

// -------------------
// SEND ALERT FUNCTION
// -------------------
function sendAlert($to, $subject, $body, $user, $pass) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.office365.com';
        $mail->SMTPAuth   = filter_var(getenv('SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($mail->SMTPAuth === null) {
            $mail->SMTPAuth = true;
        }
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
        $mail->Port       = (int)(getenv('SMTP_PORT') ?: 587);

        $mail->setFrom($user, 'PHP Monitor');
        $mail->addAddress($to);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        echo "Alert email sent to $to\n";
    } catch (Exception $e) {
        echo "Failed to send email. Error: {$mail->ErrorInfo}\n";
    }
}

// -------------------
// Infinite monitoring loop for multiple sites
// -------------------
while (true) {
    echo "Starting new monitoring cycle for all sites...\n";

    foreach ($sites as $site) {
        $target = $site['target'];
        $monitorType = strtoupper($site['monitorType'] ?? 'PING');

        $failCount = 0;

        echo "Starting new monitoring cycle for [$target] using method [$monitorType]...\n";

        for ($i = 1; $i <= $maxAttempts; $i++) {
            if ($monitorType === 'PING') {
                $isUp = isHostUp($target);
            } elseif ($monitorType === 'WEB') {
                $url = (stripos($target, 'http') === 0) ? $target : "https://$target";
                $isUp = isSiteUp($url);
            } else {
                echo "Unknown MONITOR_TYPE: $monitorType. Skipping $target.\n";
                break;
            }

            if ($isUp) {
                echo date('Y-m-d H:i:s') . " - Attempt $i: $target is UP\n";
            } else {
                echo date('Y-m-d H:i:s') . " - Attempt $i: $target is DOWN\n";
                $failCount++;
            }

            if ($i < $maxAttempts) {
                sleep($checkInterval);
            }
        }

        // Update site-specific status data based on overall cycle result
        $allStatusData[$target]['totalChecks'] += 1;

        if ($failCount === $maxAttempts) {
            // All attempts failed => site is DOWN
            $allStatusData[$target]['uptimeCycles'] = 0;
            $allStatusData[$target]['lastStatus'] = 'DOWN';
        } else {
            // Site UP this cycle
            if ($allStatusData[$target]['lastStatus'] === 'DOWN') {
                $allStatusData[$target]['uptimeCycles'] = 1;
            } else {
                $allStatusData[$target]['uptimeCycles']++;
            }
            $allStatusData[$target]['lastStatus'] = 'UP';
            $allStatusData[$target]['successfulChecks'] += 1;
        }

        $allStatusData[$target]['lastChecked'] = date('Y-m-d H:i:s');

        // Save after every site update to ensure persistence in case of crash
        file_put_contents($statusFile, json_encode($allStatusData, JSON_PRETTY_PRINT));

        // Send alert email if site is DOWN this cycle
        if ($failCount === $maxAttempts) {
            $subject = "ALERT: $target is DOWN";
            $body    = "The host $target failed all $maxAttempts monitoring attempts using method [$monitorType] within this cycle at {$allStatusData[$target]['lastChecked']}.";
            sendAlert($alertEmail, $subject, $body, $email_user, $email_pass);
        }

        echo "Cycle complete for $target.\n\n";
    }

    echo "All sites cycle complete. Waiting {$cycleDelay} seconds before next cycle...\n\n";
    sleep($cycleDelay);
}
