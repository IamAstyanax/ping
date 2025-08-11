<?php
$statusFile = '/app/status.json';
$companyName = getenv('COMPANY_NAME') ?: 'IamAstyanax';

// Load the status for all sites or initialize empty array
if (file_exists($statusFile)) {
    $allSitesStatus = json_decode(file_get_contents($statusFile), true);
} else {
    $allSitesStatus = [];
}

// Helper to calculate uptime percentage (five nines goal = 99.999%)
function calculateUptimePercent($successful, $total) {
    if ($total === 0) return "N/A";
    return round(($successful / $total) * 100, 3);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>PING - The Open Source Monitoring Tool!</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e2f;
            color: #f0f0f5;
            margin: 0;
            padding: 2rem;
        }
        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 900;
            letter-spacing: 2px;
            color: #00ffa3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: auto;
            box-shadow: 0 0 15px rgba(0,255,163,0.2);
        }
        th, td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #333;
        }
        th {
            background: #0d0d14;
            font-weight: 700;
            letter-spacing: 1px;
        }
        tr:hover {
            background: #272736;
        }
        .status-up {
            color: #00ff79;
            font-weight: 700;
            text-shadow: 0 0 5px #00ff79;
        }
        .status-down {
            color: #ff3b3b;
            font-weight: 700;
            text-shadow: 0 0 5px #ff3b3b;
        }
        .uptime-bar {
            height: 20px;
            background: #444;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.3rem;
        }
        .uptime-fill {
            height: 100%;
            background: #00ff79;
            border-radius: 10px 0 0 10px;
            transition: width 0.5s ease-in-out;
        }
        .uptime-low {
            background: #ff3b3b !important;
        }
        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: #555;
        }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($companyName) ?></h1>
    <table>
        <thead>
            <tr>
                <th>Site</th>
                <th>Status</th>
                <th>Uptime Cycles</th>
                <th>Last Checked</th>
                <th>Monitoring Started</th>
                <th title="Monitoring started at the timestamp below">
                    Uptime % (since start)
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (empty($allSitesStatus)) {
                echo '<tr><td colspan="6">No data available yet.</td></tr>';
            } else {
                foreach ($allSitesStatus as $site => $data) {
                    $statusClass = strtolower($data['lastStatus'] ?? '') === 'up' ? 'status-up' : 'status-down';
                    $uptimePercent = calculateUptimePercent(
                        $data['successfulChecks'] ?? 0, 
                        $data['totalChecks'] ?? 0
                    );
                    // color red if uptime < 99.99%
                    $uptimeBarClass = ($uptimePercent !== "N/A" && $uptimePercent < 99.99) ? 'uptime-low' : '';

                    // Uptime bar width capped to 100%
                    $barWidth = ($uptimePercent === "N/A") ? 0 : min(100, $uptimePercent);

                    $monitoringStarted = htmlspecialchars($data['serviceStartedAt'] ?? 'Unknown');

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($site) . "</td>";
                    echo "<td class='$statusClass'>" . htmlspecialchars($data['lastStatus'] ?? 'UNKNOWN') . "</td>";
                    echo "<td>" . (int)($data['uptimeCycles'] ?? 0) . "</td>";
                    echo "<td>" . htmlspecialchars($data['lastChecked'] ?? 'Never') . "</td>";
                    echo "<td>$monitoringStarted</td>";
                    echo "<td>";
                    echo number_format($uptimePercent, 3) . "%";
                    echo "<div class='uptime-bar'><div class='uptime-fill $uptimeBarClass' style='width: {$barWidth}%;'></div></div>";
                    echo "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>

    <div class="footer">
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($companyName) ?> &mdash;
    </div>
</body>
</html>
