<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class IngestController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function ticket(): void
    {
        $this->request->allowMethod(['post']);
        $body = (array)$this->request->getData();
        $logs = [];

        // Accept either JSON fields or a flat text blob from OCR for a quick prototype
        $text = (string)($body['text'] ?? '');
        $journey = (array)($body['journey'] ?? []);
        $meta = (array)($body['meta'] ?? []);

        // Minimal text heuristics to populate Bilag II, del I hooks for Art. 9(1)
        if ($text !== '') {
            $map = (new \App\Service\OcrHeuristicsMapper())->mapText($text);
            foreach (($map['auto'] ?? []) as $k => $v) {
                $meta['_auto'][$k] = $v;
            }
            $logs = array_merge($logs, $map['logs'] ?? []);
        }

        // Provide a simple journey scaffold if missing
        if (empty($journey['segments'])) { $journey['segments'] = []; }
        $journey += ['sourceHashes' => []];

        $out = ['journey' => $journey, 'meta' => $meta, 'logs' => $logs];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}
