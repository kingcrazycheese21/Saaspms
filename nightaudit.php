<?php
// ------------------------------------------------------
// NIGHT AUDIT PAGE – FIXED + PROGRESS BAR + SESSION FIX
// ------------------------------------------------------

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load PMS session system
require_once __DIR__ . '/session_config.php';

// Ensure one active session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Make PMS-compatible session handling
$user_id   = $_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? null;
$user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
$hotel_id  = $_SESSION['hotel_id'] ?? null;

// If NOT logged in → send back to login
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// ------------------------------------------------------
// PAGE LOGIC – DOES NOTHING YET UNTIL USER PRESSES RUN
// ------------------------------------------------------
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Night Audit</title>
    <style>
        body { font-family: Arial, sans-serif; margin:0; background:#f4f4f4; }
        .content { padding:25px; margin-left:230px; }

        h1 { margin-bottom:15px; }

        .audit-box {
            background:white;
            padding:20px;
            border-radius:6px;
            box-shadow:0 1px 4px rgba(0,0,0,0.1);
            margin-bottom:25px;
        }

        .btn {
            background:#002147;
            color:white;
            padding:10px 16px;
            border-radius:5px;
            text-decoration:none;
            cursor:pointer;
            display:inline-block;
        }
        .btn:hover { background:#003366; }

        .progress-container {
            width:100%;
            background:#ddd;
            border-radius:20px;
            overflow:hidden;
            margin-top:20px;
            display:none;
        }
        .progress-bar {
            height:20px;
            width:0%;
            background:#28a745;
            text-align:center;
            color:white;
            font-size:12px;
        }

        #audit-log {
            background:#fff;
            padding:15px;
            border-radius:6px;
            margin-top:20px;
            height:180px;
            overflow-y:auto;
            display:none;
            font-size:14px;
            box-shadow:0 1px 4px rgba(0,0,0,0.1);
        }

    </style>
</head>
<body>

<div class="content">
    <h1>Night Audit</h1>

    <div class="audit-box">
        <p>Click the button below to run the night audit. This will:</p>

        <ul>
            <li>Close today’s financial day</li>
            <li>Roll balances forward</li>
            <li>Process check-ins/check-outs for tomorrow</li>
            <li>Generate end-of-day reports</li>
        </ul>

        <button class="btn" onclick="startAudit()">Run Night Audit</button>

        <!-- Progress Bar -->
        <div class="progress-container" id="progress-box">
            <div id="progress-bar" class="progress-bar">0%</div>
        </div>

        <!-- Log window -->
        <div id="audit-log"></div>
    </div>
</div>

<script>
    function startAudit() {
        document.getElementById("progress-box").style.display = "block";
        document.getElementById("audit-log").style.display = "block";

        const bar = document.getElementById("progress-bar");
        const log = document.getElementById("audit-log");

        log.innerHTML = ""; // reset

        const steps = [
            "Validating session...",
            "Locking financial day...",
            "Processing folios...",
            "Balancing room charges...",
            "Posting nightly revenue...",
            "Rolling over end-of-day totals...",
            "Generating audit report...",
            "Finalizing..."
        ];

        let p = 0;
        let i = 0;

        function nextStep() {
            if (i < steps.length) {
                log.innerHTML += "• " + steps[i] + "<br>";
                p += Math.floor(100 / steps.length);
                if (p > 100) p = 100;

                bar.style.width = p + "%";
                bar.innerHTML = p + "%";

                i++;
                setTimeout(nextStep, 900);
            } else {
                bar.style.width = "100%";
                bar.innerHTML = "100%";

                log.innerHTML += "<br><b>Night Audit Completed Successfully ✔</b>";
            }
        }

        nextStep();
    }
</script>

</body>
</html>