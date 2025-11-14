<?php
declare(strict_types=1);

namespace App\Controller\Admin;

class FlowAdminController extends \App\Controller\AppController
{
    public function index(): \Cake\Http\Response|null
    {
        $sess = $this->request->getSession();
        $isAdminMode = (bool)$sess->read('admin.mode');
        $flow = [
            'form' => (array)$sess->read('flow.form') ?: [],
            'journey' => (array)$sess->read('flow.journey') ?: [],
            'meta' => (array)$sess->read('flow.meta') ?: [],
            'compute' => (array)$sess->read('flow.compute') ?: [],
            'flags' => (array)$sess->read('flow.flags') ?: [],
            'incident' => (array)$sess->read('flow.incident') ?: [],
        ];
        $this->set(compact('isAdminMode','flow'));
        return null;
    }

    public function toggle(): \Cake\Http\Response|null
    {
        if ($this->request->is('post')) {
            $sess = $this->request->getSession();
            $cur = (bool)$sess->read('admin.mode');
            $sess->write('admin.mode', !$cur);
            return $this->redirect(['action' => 'index']);
        }
        return $this->redirect(['action' => 'index']);
    }
}
