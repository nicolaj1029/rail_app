<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Utility\Text;

class ManualJourneysController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        // Accept only POST (and OPTIONS)
        $this->request->allowMethod(['post', 'options']);
    }

    /**
     * POST /api/manual_journeys
     * Accepts manual journey payload (JSON or form-data) plus optional ticket/receipt files.
     * Current implementation just stores the payload in tmp/manual_journeys and returns a stub case_id.
     */
    public function add()
    {
        $data = (array)$this->request->getData();

        // Persist raw payload for debugging
        $dir = ROOT . DS . 'tmp' . DS . 'manual_journeys';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $id = Text::uuid();
        $payloadFile = $dir . DS . $id . '.json';
        @file_put_contents($payloadFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Store ticket/receipts if present
        foreach (['ticket_image', 'image', 'ticket'] as $key) {
            $file = $data[$key] ?? null;
            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                $ext = pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION);
                $filename = $dir . DS . $id . '_ticket.' . ($ext ?: 'bin');
                $file->moveTo($filename);
                break;
            }
        }
        if (isset($data['receipts']) && is_array($data['receipts'])) {
            $i = 0;
            foreach ($data['receipts'] as $rec) {
                if (is_object($rec) && method_exists($rec, 'getError') && $rec->getError() === UPLOAD_ERR_OK) {
                    $ext = pathinfo((string)$rec->getClientFilename(), PATHINFO_EXTENSION);
                    $filename = $dir . DS . $id . '_receipt_' . $i . '.' . ($ext ?: 'bin');
                    $rec->moveTo($filename);
                    $i++;
                }
            }
        }

        $this->set([
            'success' => true,
            'data' => [
                'case_id' => $id,
                'stored' => basename($payloadFile),
            ],
        ]);
    }
}
