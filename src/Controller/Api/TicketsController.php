<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use Cake\Utility\Text;

class TicketsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        // Accept only POST (and OPTIONS for preflight)
        $this->request->allowMethod(['post', 'options']);
    }

    /**
     * POST /api/tickets/match
     * Multipart upload with "image" (ticket photo/pdf) and optional device_id.
     * Currently a stub that saves the file to tmp/uploads and returns a fake match result.
     */
    public function match()
    {
        $data = (array)$this->request->getData();
        $file = $data['image'] ?? null;
        $deviceId = (string)($data['device_id'] ?? '');

        $savedPath = null;
        if ($file && $file->getError() === UPLOAD_ERR_OK) {
            $dir = ROOT . DS . 'tmp' . DS . 'uploads' . DS . 'tickets';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $ext = pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION);
            $filename = Text::uuid() . ($ext ? '.' . $ext : '');
            $target = $dir . DS . $filename;
            $file->moveTo($target);
            $savedPath = $target;
        }

        $this->set([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'saved' => $savedPath,
                // Stubbed match response
                'match' => [
                    'status' => $savedPath ? 'matched_stub' : 'no_file',
                    'journey' => null,
                ],
            ],
        ]);
    }
}
