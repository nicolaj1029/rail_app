<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Core\Configure;

final class RailTransportServiceManager
{
    /**
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $pid = $this->readPid();
        $pidSource = 'pid_file';

        if ($pid === null || !$this->processExists($pid)) {
            $pid = $this->findPidByPort();
            $pidSource = $pid !== null ? 'port_probe' : 'none';
        }

        $running = $pid !== null && $this->processExists($pid);

        return [
            'pid' => $pid,
            'running' => $running,
            'pid_source' => $pidSource,
            'port' => $this->port(),
            'node_binary' => $this->nodeBinary(),
            'pid_file' => $this->pidFile(),
            'stdout_log' => $this->stdoutLogFile(),
            'stderr_log' => $this->stderrLogFile(),
            'workdir' => $this->serviceDir(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function start(): array
    {
        $current = $this->status();
        if (!empty($current['running'])) {
            return [
                'ok' => true,
                'message' => 'Rail transport service kører allerede.',
            ] + $current;
        }

        $serviceDir = $this->serviceDir();
        if (!is_dir($serviceDir)) {
            return [
                'ok' => false,
                'message' => 'Service-mappen findes ikke.',
            ];
        }

        $nodeBinary = $this->nodeBinary();
        if ($nodeBinary === '' || !is_file($nodeBinary)) {
            return [
                'ok' => false,
                'message' => 'Node.js-binær kunne ikke findes. Sæt evt. RAIL_TRANSPORT_NODE_BIN.',
            ];
        }

        $psScript = sprintf(
            "\$p = Start-Process -FilePath '%s' -ArgumentList 'src/server.mjs' -WorkingDirectory '%s' -WindowStyle Hidden -RedirectStandardOutput '%s' -RedirectStandardError '%s' -PassThru; \$p.Id",
            str_replace("'", "''", $nodeBinary),
            str_replace("'", "''", $serviceDir),
            str_replace("'", "''", $this->stdoutLogFile()),
            str_replace("'", "''", $this->stderrLogFile())
        );

        $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript);
        $output = [];
        $exitCode = 1;
        @exec($command . ' 2>&1', $output, $exitCode);
        $pid = isset($output[0]) ? (int)trim((string)$output[0]) : 0;

        if ($exitCode !== 0 || $pid <= 0) {
            return [
                'ok' => false,
                'message' => 'Kunne ikke starte rail transport service.',
            ];
        }

        @file_put_contents($this->pidFile(), (string)$pid);
        usleep(500000);

        $status = $this->status();

        return [
            'ok' => !empty($status['running']),
            'message' => !empty($status['running'])
                ? 'Rail transport service er startet.'
                : 'Service-processen blev oprettet, men svarer ikke endnu.',
        ] + $status;
    }

    /**
     * @return array<string,mixed>
     */
    public function stop(): array
    {
        $status = $this->status();
        $pid = isset($status['pid']) ? (int)$status['pid'] : 0;

        if ($pid <= 0) {
            return [
                'ok' => true,
                'message' => 'Ingen rail transport service PID fundet.',
            ];
        }

        $output = [];
        $shutdownViaHttp = false;

        if ((new RailTransportServiceClient())->shutdown()) {
            $shutdownViaHttp = true;
            usleep(1200000);
        }

        $running = $this->processExists($pid);

        if ($running) {
            $psScript = sprintf(
                'if (Get-Process -Id %d -ErrorAction SilentlyContinue) { Stop-Process -Id %d -Force }',
                $pid,
                $pid
            );
            $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript);
            $psOutput = [];
            $exitCode = 1;
            @exec($command . ' 2>&1', $psOutput, $exitCode);
            $output = array_merge($output, $psOutput);
            usleep(600000);
            $running = $this->processExists($pid);
        }

        if ($running) {
            $taskkillOutput = [];
            $taskkillExit = 1;
            @exec(sprintf('cmd.exe /c taskkill /PID %d /T /F 2>&1', $pid), $taskkillOutput, $taskkillExit);
            $output = array_merge($output, $taskkillOutput);
            usleep(600000);
            $running = $this->processExists($pid);
        }

        if (!$running) {
            @unlink($this->pidFile());
        }

        $message = !$running
            ? ($shutdownViaHttp
                ? 'Rail transport service er stoppet via service-shutdown.'
                : 'Rail transport service er stoppet.')
            : 'Service-processen kunne ikke stoppes.';
        $combinedOutput = strtolower(implode(' ', array_map('strval', $output)));
        if ($running && (str_contains($combinedOutput, 'adgang nægtet') || str_contains($combinedOutput, 'access is denied'))) {
            $message = 'Service-processen blev ikke stoppet fra Admin Desk. Den kører sandsynligvis i et manuelt terminalvindue. Stop den dér først.';
        }

        return [
            'ok' => !$running,
            'pid' => $pid,
            'running' => $running,
            'message' => $message,
        ];
    }

    private function readPid(): ?int
    {
        $file = $this->pidFile();
        if (!is_file($file)) {
            return null;
        }

        $pid = (int)trim((string)@file_get_contents($file));

        return $pid > 0 ? $pid : null;
    }

    private function processExists(int $pid): bool
    {
        $psScript = sprintf(
            "if (Get-Process -Id %d -ErrorAction SilentlyContinue) { 'yes' } else { 'no' }",
            $pid
        );
        $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript);
        $output = [];
        $exitCode = 1;
        @exec($command . ' 2>&1', $output, $exitCode);

        return strtolower(trim((string)($output[0] ?? 'no'))) === 'yes';
    }

    private function pidFile(): string
    {
        return TMP . 'rail_transport_service.pid';
    }

    private function stdoutLogFile(): string
    {
        return TMP . 'rail_transport_service.stdout.log';
    }

    private function stderrLogFile(): string
    {
        return TMP . 'rail_transport_service.stderr.log';
    }

    private function port(): int
    {
        $baseUrl = (string)Configure::read('Rail.transportServiceBaseUrl', 'http://127.0.0.1:7071');

        return (int)(parse_url($baseUrl, PHP_URL_PORT) ?: 7071);
    }

    private function findPidByPort(): ?int
    {
        $port = $this->port();
        $psScript = sprintf(
            '$conn = Get-NetTCPConnection -LocalPort %d -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1; if ($conn) { $conn.OwningProcess }',
            $port
        );
        $command = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psScript);
        $output = [];
        $exitCode = 1;
        @exec($command . ' 2>&1', $output, $exitCode);
        $pid = isset($output[0]) ? (int)trim((string)$output[0]) : 0;
        if ($pid > 0) {
            return $pid;
        }

        $netstatOutput = [];
        $netstatExit = 1;
        @exec(sprintf('cmd.exe /c netstat -ano | findstr ":%d"', $port), $netstatOutput, $netstatExit);
        if ($netstatExit !== 0) {
            return null;
        }

        foreach ($netstatOutput as $line) {
            if (preg_match('/LISTENING\s+(\d+)\s*$/i', $line, $matches)) {
                $pid = (int)$matches[1];
                if ($pid > 0) {
                    return $pid;
                }
            }
        }

        return null;
    }

    private function nodeBinary(): string
    {
        $configured = trim((string)env('RAIL_TRANSPORT_NODE_BIN', ''));
        if ($configured !== '') {
            return $configured;
        }

        $candidates = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function serviceDir(): string
    {
        return ROOT . DS . 'services' . DS . 'rail-transport-service';
    }
}
