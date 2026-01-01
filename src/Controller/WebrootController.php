<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;

class WebrootController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->disableAutoLayout();
    }

    // Serve files from webroot/project/<slug>/index.html
    public function project(string $slug = null)
    {
        if ($slug === null) {
            throw new NotFoundException('Missing project path');
        }

        $path = WWW_ROOT . 'project' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($path)) {
            throw new NotFoundException('QA page not found');
        }

        $this->response = $this->response->withType('html');
        $this->response = $this->response->withStringBody((string)file_get_contents($path));
        return $this->response;
    }
}
