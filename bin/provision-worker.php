<?php

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/provisioning_jobs.php";
require_once __DIR__ . "/../lib/game_server_provisioning.php";

$pdo = db();

$workerId = gethostname()
    . "-"
    . getmypid()
    . "-"
    . bin2hex(random_bytes(3));

$once = in_array("--once", $argv, true);
$lastRecoveryAt = 0;

echo "[HC Provision Worker] started: {$workerId}\n";

try {
    $recoveredCount = hc_recover_stale_provisioning_jobs(
        $pdo,
        10
    );

    if ($recoveredCount > 0) {
        echo "停止中ジョブを {$recoveredCount} 件復旧しました。\n";
    }
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "[Stale Job Recovery Error] "
        . $e->getMessage()
        . PHP_EOL
    );
}

do {
    try {
        if (!$once && time() - $lastRecoveryAt >= 60) {
            $recoveredCount = hc_recover_stale_provisioning_jobs(
                $pdo,
                10
            );

            if ($recoveredCount > 0) {
                echo "停止中ジョブを {$recoveredCount} 件復旧しました。\n";
            }

            $lastRecoveryAt = time();
        }

        $job = hc_claim_provisioning_job($pdo, $workerId);

        if (!$job) {
            if ($once) {
                echo "処理対象ジョブはありません。\n";
                break;
            }

            sleep(3);
            continue;
        }

        $jobId = (int)$job["id"];
        $orderId = (int)$job["order_id"];

        echo "Job #{$jobId} / Order #{$orderId} を処理します。\n";

        $result = provision_game_server_order($pdo, $orderId);

        if (!empty($result["ok"])) {
            hc_complete_provisioning_job($pdo, $jobId);

            echo "Job #{$jobId} 完了\n";
        } else {
            $error = (string)($result["error"] ?? "不明なエラー");

            hc_fail_provisioning_job($pdo, $jobId, $error);

            fwrite(
                STDERR,
                "Job #{$jobId} 失敗: {$error}\n"
            );
        }
    } catch (Throwable $e) {
        fwrite(
            STDERR,
            "[Worker Error] " . $e->getMessage() . PHP_EOL
        );

        if ($once) {
            exit(1);
        }

        sleep(5);
    }
} while (!$once);
