<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Console\{Arguments, ConsoleIo, BaseCommand};
use GuzzleHttp\Client;

final class AiAuditCommand extends BaseCommand
{
    protected string $defaultName = 'ai_audit';
    
    /**
     * Return first non-empty environment value from a list of names.
     * Checks getenv(), $_ENV and $_SERVER, and also lower-case variants on case-insensitive systems.
     */
    private function envAny(array $names): string
    {
        foreach ($names as $n) {
            foreach ([$n, strtolower($n)] as $key) {
                $v = getenv($key);
                if ($v === false || $v === '') {
                    $v = $_ENV[$key] ?? $_SERVER[$key] ?? '';
                }
                if ($v !== '' && $v !== false) {
                    return trim((string)$v);
                }
            }
        }
        return '';
    }
    
    protected function buildOptionParser(\Cake\Console\ConsoleOptionParser $parser): \Cake\Console\ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser->addOptions([
            'provider' => [
                'help' => 'Force provider: groq|openai|none',
                'short' => 'p',
            ],
            'insecure' => [
                'help' => 'Disable TLS certificate verification for HTTP calls (local dev only).',
                'boolean' => true,
            ],
            'groq-key' => [
                'help' => 'Override Groq API key (use quotes). Safer for one-off runs than setting env.',
            ],
            'openai-key' => [
                'help' => 'Override OpenAI API key (use quotes).',
            ],
            'groq-model' => [
                'help' => 'Groq model override (default: mixtral-8x7b-32768).',
            ],
            'openai-model' => [
                'help' => 'OpenAI model override (default: gpt-4o-mini).',
            ],
        ]);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        $io->out('▶ Kører PHPUnit…');
        // Ensure logs dir exists
        if (!is_dir(LOGS)) { @mkdir(LOGS, 0775, true); }
        $junit = LOGS . 'junit.xml';
        $txt   = LOGS . 'phpunit.txt';
        $cmd   = sprintf('vendor%1$sbin%1$sphpunit --colors=never --log-junit %2$s > %3$s 2>&1', DIRECTORY_SEPARATOR, escapeshellarg($junit), escapeshellarg($txt));
        // On Windows, ensure .bat is used when available
        if (str_starts_with(PHP_OS_FAMILY, 'Windows')) {
            $cmd = sprintf('vendor%1$sbin%1$sphpunit.bat -v --colors=never --log-junit %2$s > %3$s 2>&1', DIRECTORY_SEPARATOR, escapeshellarg($junit), escapeshellarg($txt));
        }
        passthru($cmd);

        $io->out('▶ Indlæser kontekst…');
        $policy = @file_get_contents(CONFIG . 'policy_context.md') ?: '';
        $matrix = @file_get_contents(CONFIG . 'data' . DIRECTORY_SEPARATOR . 'exemption_matrix.json') ?: '';
        $over   = @file_get_contents(CONFIG . 'data' . DIRECTORY_SEPARATOR . 'national_overrides.json') ?: '';
        // support both config/data/rules_snapshot.txt and docs/rules_snapshot.txt
        $snapPath = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'rules_snapshot.txt';
        if (!is_file($snapPath)) {
            $alt = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'rules_snapshot.txt';
            if (is_file($alt)) { $snapPath = $alt; }
        }
        $snap   = @file_get_contents($snapPath) ?: '';
        $junitXml = @file_get_contents($junit) ?: '';
        $testTxt  = @file_get_contents($txt) ?: '';

        $io->out('▶ Samler hooks/fixtures…');
        $extra = $this->collectAuditContext($io);

        $prompt = $this->buildPrompt($policy, $matrix, $over, $snap, $junitXml, $testTxt)
            . "\n\n### ADDITIONAL CONTEXT (hooks/fixtures/etc)\n" . implode("\n\n", $extra);

        // Graceful degrade: if no AI keys configured, save a placeholder report and exit success
    $forcedGroqKey = (string)($args->getOption('groq-key') ?? '');
    $forcedOpenAIKey = (string)($args->getOption('openai-key') ?? '');
    $hasGroq = $forcedGroqKey !== '' ? trim($forcedGroqKey) : $this->envAny(['GROQ_API_KEY', 'GROQ_KEY', 'GROQ_TOKEN', 'GROQ_API_TOKEN']);
    $hasOpenAI = $forcedOpenAIKey !== '' ? trim($forcedOpenAIKey) : $this->envAny(['OPENAI_API_KEY', 'OPENAI_KEY']);
        $mk = function(string $k): string { return strlen($k) > 8 ? (substr($k,0,4).'…'.substr($k,-4)) : ($k!==''?'[set]':'[none]'); };
        $io->out('▶ Keys: GROQ=' . $mk($hasGroq) . ' OPENAI=' . $mk($hasOpenAI));
        if ($hasGroq === '' && $hasOpenAI === '') {
            $placeholder = "AI audit disabled (no API keys configured).\n\n" .
                "You can enable Groq/OpenAI by setting GROQ_API_KEY / OPENAI_API_KEY and running again.\n\n" .
                "PHPUnit JUnit: $junit\nPHPUnit Log: $txt\n";
            $file = $this->writeAuditFile($placeholder);
            $this->writeLinksHtml($file, $junit, $txt);
            $io->warning('Ingen AI-nøgle sat – gemmer placeholder-rapport og fortsætter.');
            $io->out(' - AI audit: ' . $file);
            $io->out(' - JUnit:    ' . $junit);
            $io->out(' - Log:      ' . $txt);
            $io->out(' - Links:    ' . (LOGS . 'links.html'));
            return self::CODE_SUCCESS;
        }

        // 1) Try Groq (or force provider if passed)
    $provider = strtolower((string)($args->getOption('provider') ?? ''));
        $groqModel = (string)($args->getOption('groq-model') ?? '');
        // If no explicit Groq key, but OPENAI_BASE_URL points to groq.com and OPENAI key is set, reuse it
        if ($hasGroq === '') {
            $obase = $this->envAny(['OPENAI_BASE_URL']);
            $okey  = $hasOpenAI;
            if ($okey !== '' && stripos($obase, 'groq.com') !== false) {
                $hasGroq = $okey;
            }
        }
        $insecure = (bool)$args->getOption('insecure') || $this->envAny(['LLM_INSECURE_SKIP_VERIFY']) === '1';
        if ($insecure) { $io->warning('TLS verification disabled for this run (insecure).'); }
        if ($provider === '' || $provider === 'groq') {
            $io->out('▶ Sender til Groq…');
        }
        $groq = ($provider === '' || $provider === 'groq') ? $this->askGroq($prompt, $io, $hasGroq, $groqModel, $insecure) : null;
        if ($groq !== null) {
            $file = $this->writeAuditFile($groq);
            $this->writeLinksHtml($file, $junit, $txt);
            $io->success("GROQ AUDIT (gemt):");
            $io->out($groq);
            $io->out("");
            $io->out('Links:');
            $io->out(' - AI audit: ' . $file);
            $io->out(' - JUnit:    ' . $junit);
            $io->out(' - Log:      ' . $txt);
            $io->out(' - Links:    ' . (LOGS . 'links.html'));
            return self::CODE_SUCCESS;
        }

        // 2) Fallback OpenAI
    $io->warning('Groq fejlede – prøver OpenAI…');
    $openaiModel = (string)($args->getOption('openai-model') ?? '');
    // Determine OpenAI base URL: prefer CLI option; avoid misrouting to groq.com when provider=openai
    $openaiBaseOpt = (string)($args->getOption('openai-base-url') ?? '');
    $openaiBase = $openaiBaseOpt !== '' ? $openaiBaseOpt : $this->envAny(['OPENAI_BASE_URL']);
    if (($provider === 'openai') && stripos((string)$openaiBase, 'groq.com') !== false) {
        $openaiBase = 'https://api.openai.com';
    }
    $openai = ($provider === '' || $provider === 'openai') ? $this->askOpenAI($prompt, $io, $hasOpenAI, $openaiModel, $insecure, $openaiBase) : null;
        if ($openai !== null) {
            $file = $this->writeAuditFile($openai);
            $this->writeLinksHtml($file, $junit, $txt);
            $io->success("OPENAI AUDIT (gemt):");
            $io->out($openai);
            $io->out("");
            $io->out('Links:');
            $io->out(' - AI audit: ' . $file);
            $io->out(' - JUnit:    ' . $junit);
            $io->out(' - Log:      ' . $txt);
            $io->out(' - Links:    ' . (LOGS . 'links.html'));
            return self::CODE_SUCCESS;
        }

    $io->error('Ingen AI-svar modtaget.');
    $io->out('Tjek venligst miljøvariablerne: GROQ_API_KEY/OPENAI_API_KEY.');
    $io->out('PHPUnit-artifakter:');
    $io->out(' - JUnit: ' . $junit);
    $io->out(' - Log:   ' . $txt);
    // Do not fail the script; write placeholder output and exit success to keep local selftest usable
    $file = $this->writeAuditFile("AI audit failed to contact providers. See logs above.\n");
    $this->writeLinksHtml($file, $junit, $txt);
    $io->out(' - AI audit placeholder: ' . $file);
    $io->out(' - Links:    ' . (LOGS . 'links.html'));
    return self::CODE_SUCCESS;
    }

    private function buildPrompt(string $policy, string $matrix, string $over, string $snap, string $junitXml, string $testTxt): string
    {
        // Compact large inputs to reduce request size
        $policy = $this->truncate($policy, 40000);
        $matrix = $this->truncate($matrix, 40000);
        $over   = $this->truncate($over, 40000);
        $snap   = $this->truncate($snap, 40000);
        $junitXml = $this->truncate($junitXml, 60000);
        $testTxt  = $this->truncate($testTxt, 20000);
        return <<<PROMPT
Du er QA-ekspert i EU-forordning (EU) 2021/782 om jernbanepassagerrettigheder.
Valider vores kompensations-/formularlogik i en CakePHP-app.

OPGAVE:
1) Find afvigelser fra forordningen og vores nationale undtagelser.
2) Peg på manglende cases i tests.
3) Giv konkrete rettelser (fil+metode+assertion) og nye testcases i punktform.
4) Markér fejl som: [BLOCKER] eller [MINOR].

KONTEKST (juridisk):
{$policy}

DATAKILDER:
exemption_matrix.json:
{$matrix}

national_overrides.json:
{$over}

rules_snapshot.txt:
{$snap}

TESTRESULTATER (JUnit):
{$junitXml}

TESTLOG (human):
{$testTxt}

Returnér:
- Kort EXECUTIVE SUMMARY (max 8 linjer)
- Liste over fejl/mangler med henvisning til Art. XX og national note
- Forslag til nye PHPUnit-tests (navn, input, forventet output)
PROMPT;
    }

    private function askGroq(string $prompt, ?ConsoleIo $io = null, ?string $overrideKey = null, ?string $overrideModel = null, bool $insecure = false, int $attemptsLeft = 2): ?string
    {
        $key = trim((string)($overrideKey ?? ''));
        if ($key === '') {
            $key = $this->envAny(['GROQ_API_KEY', 'GROQ_KEY', 'GROQ_TOKEN', 'GROQ_API_TOKEN']);
        }
        $model = trim((string)($overrideModel ?? ''));
        if ($model === '') {
            // Prefer specific Groq model var, then OPENAI_MODEL if using OpenAI-compatible config, then a safe default
            $model = $this->envAny(['GROQ_MODEL']);
            if ($model === '') { $model = $this->envAny(['OPENAI_MODEL']); }
            if ($model === '') { $model = 'llama-3.1-8b-instant'; }
        }
        if (!$key) return null;

        // Base URL handling: prefer GROQ_BASE_URL then OPENAI_BASE_URL if pointing to groq.com, else default
        $base = $this->envAny(['GROQ_BASE_URL']);
        if ($base === '') {
            $proxyBase = $this->envAny(['OPENAI_BASE_URL']);
            if ($proxyBase !== '' && stripos($proxyBase, 'groq.com') !== false) {
                $base = $proxyBase;
            }
        }
        if ($base === '') {
            $base = 'https://api.groq.com';
        }
        $endpoint = rtrim($base, '/') . '/openai/v1/chat/completions';

        try {
            $client = new Client();
            $verify = $this->buildVerifyOption($insecure);
            $res = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$key}",
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a precise compliance QA for EU rail regulation.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'temperature' => 0,
                ],
                'timeout' => 90,
                'verify' => $verify,
            ]);
            $raw = (string)$res->getBody();
            $payload = json_decode($raw, true);
            $content = $payload['choices'][0]['message']['content'] ?? null;
            if ($content === null) {
                if ($io) {
                    $masked = (strlen($key) > 8) ? (substr($key, 0, 4) . '…' . substr($key, -4)) : '[set]';
                    $preview = substr($raw, 0, 400);
                    @file_put_contents(LOGS . 'ai_audit_last_error.txt', '[Groq HTTP ' . $res->getStatusCode() . "] Body: " . $preview);
                    $io->warning('Groq returned no content (see ai_audit_last_error.txt).');
                    $io->out('Key detected: ' . $masked);
                }
            }
            return $content;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle 413 (payload too large) by auto-switching to a larger-context model once
            $resp = $e->getResponse();
            $code = $resp ? $resp->getStatusCode() : 0;
            $bodyPreview = $resp ? substr((string)$resp->getBody(), 0, 400) : '';
            if ($code === 413 && stripos($model, '70b') === false && $attemptsLeft > 0) {
                $bigger = 'llama-3.1-70b-versatile';
                if ($io) {
                    $io->warning('Groq 413: switching model to ' . $bigger . ' and retrying…');
                }
                return $this->askGroq($prompt, $io, $key, $bigger, $insecure, $attemptsLeft - 1);
            }
            // Handle decommissioned/unsupported model by trying a modern fallback list
            if ($code === 400 && (stripos($bodyPreview, 'decommissioned') !== false || stripos($bodyPreview, 'not supported') !== false) && $attemptsLeft > 0) {
                $candidates = [
                    'llama-3.2-90b-text-preview',
                    'llama-3.2-11b-text-preview',
                    'llama-3.1-8b-instant',
                ];
                foreach ($candidates as $cand) {
                    if (strcasecmp($cand, $model) === 0) { continue; }
                    if ($io) { $io->warning('Groq 400: switching model to ' . $cand . ' and retrying…'); }
                    $out = $this->askGroq($prompt, $io, $key, $cand, $insecure, $attemptsLeft - 1);
                    if ($out !== null) { return $out; }
                }
            }
            if ($io) {
                $masked = (strlen($key) > 8) ? (substr($key, 0, 4) . '…' . substr($key, -4)) : '[set]';
                @file_put_contents(LOGS . 'ai_audit_last_error.txt', '[Groq ' . $code . "] Body: " . $bodyPreview);
                $io->warning('Groq error: ' . $e->getMessage());
                $io->out('Key detected: ' . $masked);
                $io->out('See: ' . LOGS . 'ai_audit_last_error.txt');
            }
            return null;
        } catch (\Throwable $e) {
            if ($io) {
                $masked = (strlen($key) > 8) ? (substr($key, 0, 4) . '…' . substr($key, -4)) : '[set]';
                @file_put_contents(LOGS . 'ai_audit_last_error.txt', '[Groq] ' . $e->getMessage());
                $io->warning('Groq error: ' . $e->getMessage());
                $io->out('Key detected: ' . $masked);
                $io->out('See: ' . LOGS . 'ai_audit_last_error.txt');
            }
            return null;
        }
    }

    private function askOpenAI(string $prompt, ?ConsoleIo $io = null, ?string $overrideKey = null, ?string $overrideModel = null, bool $insecure = false, ?string $overrideBase = null): ?string
    {
        $key = trim((string)($overrideKey ?? ''));
        if ($key === '') {
            $key = $this->envAny(['OPENAI_API_KEY', 'OPENAI_KEY']);
        }
        $model = trim((string)($overrideModel ?? ''));
        if ($model === '') {
            $model = $this->envAny(['OPENAI_MODEL']) ?: 'gpt-4o-mini';
        }
    if (!$key) return null;

        // Support OPENAI_BASE_URL override for compatibility (e.g., Azure/OpenAI-compatible endpoints)
        $base = trim((string)($overrideBase ?? ''));
        if ($base === '') {
            $base = $this->envAny(['OPENAI_BASE_URL']);
        }
    if ($base === '') { $base = 'https://api.openai.com'; }
    $endpoint = rtrim($base, '/') . '/v1/chat/completions';

        try {
            $client = new Client();
            $verify = $this->buildVerifyOption($insecure);
            $res = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$key}",
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a precise compliance QA for EU rail regulation.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                    'temperature' => 0,
                ],
                'timeout' => 90,
                'verify' => $verify,
            ]);
            $payload = json_decode((string)$res->getBody(), true);
            return $payload['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            if ($io) {
                @file_put_contents(LOGS . 'ai_audit_last_error.txt', '[OpenAI] ' . $e->getMessage());
                $io->warning('OpenAI error: ' . $e->getMessage());
                $io->out('See: ' . LOGS . 'ai_audit_last_error.txt');
            }
            return null;
        }
    }

    /**
     * Determine Guzzle 'verify' option based on env or --insecure flag.
     * Returns false to disable verification, true to use system, or a path to CA bundle.
     */
    private function buildVerifyOption(bool $insecure)
    {
        if ($insecure) {
            return false;
        }
        $bundle = $this->envAny(['CURL_CA_BUNDLE', 'SSL_CERT_FILE']);
        if ($bundle !== '' && file_exists($bundle)) {
            return $bundle;
        }
        return true;
    }

    /**
     * Collect compact snippets of fixtures, PHPUnit hooks, Cake hooks/events, and config.
     */
    private function collectAuditContext(ConsoleIo $io): array
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            'fixtures' => glob($root . '/tests/Fixture/*.php') ?: [],
            'tests'    => glob($root . '/tests/TestCase/**/*.php') ?: [],
            'config'   => array_values(array_filter([
                CONFIG . 'bootstrap.php',
                CONFIG . 'bootstrap_cli.php',
                $root . '/phpunit.xml', $root . '/phpunit.xml.dist',
            ], 'file_exists')),
            'src'      => array_merge(
                glob($root . '/src/Application.php') ?: [],
                glob($root . '/src/Event/*.php') ?: [],
                glob($root . '/src/Controller/*.php') ?: []
            ),
            'plugins'  => glob($root . '/plugins/*/src/**/**.php') ?: [],
        ];

        $snippets = [];

        foreach ($paths['fixtures'] as $f) {
            $code = @file_get_contents($f) ?: '';
            $snippets[] = '### FIXTURE: ' . basename($f) . "\n" . $this->extractFixtureSchemaAndRecords($code);
        }

        foreach ($paths['tests'] as $t) {
            $code = @file_get_contents($t) ?: '';
            $hooks = $this->extractPhpunitHooks($code);
            if ($hooks) {
                $snippets[] = '### TEST HOOKS: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $t) . "\n" . $hooks;
            }
        }

        foreach (array_merge($paths['src'], $paths['plugins']) as $p) {
            $code = @file_get_contents($p) ?: '';
            if ($code === '') { continue; }
            $hit = $this->extractCakeHooksAndEvents($code);
            if ($hit) {
                $snippets[] = '### CAKE HOOKS/EVENTS: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $p) . "\n" . $hit;
            }
        }

        foreach ($paths['config'] as $c) {
            $snippets[] = '### CONFIG: ' . basename($c) . "\n" . $this->safeRead($c, 40000);
        }

        return $snippets;
    }

    private function extractFixtureSchemaAndRecords(string $code): string
    {
        $schema = $this->grepBlock($code, 'public $fields', ';', 6000);
        $records = $this->grepBlock($code, 'public $records', ';', 6000);
        return "FIELDS:\n{$schema}\nRECORDS:\n{$records}\n";
    }

    private function extractPhpunitHooks(string $code): string
    {
        $out = [];
        foreach (['setUp', 'tearDown', 'setUpBeforeClass', 'tearDownAfterClass'] as $m) {
            if (preg_match('/function\s+' . $m . '\s*\([^)]*\)\s*\{(.+?)\}/s', $code, $mch)) {
                $out[] = 'function ' . $m . '() {' . $this->truncate($mch[1]) . '}';
            }
        }
        return implode("\n\n", $out);
    }

    private function extractCakeHooksAndEvents(string $code): string
    {
        $snips = [];
        foreach (['beforeFilter','afterFilter','beforeRender','afterRender','initialize','implementedEvents'] as $m) {
            if (preg_match('/function\s+' . $m . '\s*\([^)]*\)\s*\{(.+?)\}/s', $code, $mch)) {
                $snips[] = 'function ' . $m . "() {\n" . $this->truncate($mch[1]) . "\n}";
            }
        }
        return implode("\n\n", $snips);
    }

    private function grepBlock(string $code, string $startNeedle, string $endChar, int $maxLen): string
    {
        $pos = strpos($code, $startNeedle);
        if ($pos === false) return '';
        $chunk = substr($code, $pos, $maxLen);
        $semi = strpos($chunk, $endChar);
        return $semi !== false ? substr($chunk, 0, $semi + 1) : $this->truncate($chunk);
    }

    private function truncate(string $s, int $limit = 2000): string
    {
        $s = trim($s);
        return strlen($s) > $limit ? substr($s, 0, $limit) . "\n/* …truncated… */" : $s;
    }

    private function safeRead(string $file, int $limit): string
    {
        $raw = @file_get_contents($file) ?: '';
        return $this->truncate($raw, $limit);
    }

    private function writeAuditFile(string $content): string
    {
        $name = 'ai_audit_' . date('Ymd_His') . '.md';
        $path = rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        @file_put_contents($path, $content);
        return $path;
    }

    private function writeLinksHtml(string $auditFile, string $junit, string $txt): void
    {
        $html = "<!doctype html>\n<html><head><meta charset=\"utf-8\"><title>AI Audit Links</title></head><body>\n".
            '<h1>AI Audit Links</h1>' . "\n<ul>\n".
            '<li><a href="' . htmlspecialchars(basename($auditFile), ENT_QUOTES) . '">AI audit (seneste)</a></li>' . "\n".
            '<li><a href="' . htmlspecialchars(basename($junit), ENT_QUOTES) . '">JUnit</a></li>' . "\n".
            '<li><a href="' . htmlspecialchars(basename($txt), ENT_QUOTES) . '">PHPUnit log</a></li>' . "\n".
            "</ul>\n</body></html>\n";
        @file_put_contents(LOGS . 'links.html', $html);
    }
}
