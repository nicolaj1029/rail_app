<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ClaimAttachmentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('claim_attachments');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Claims');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('type')->notEmptyString('type');
        $validator->scalar('path')->notEmptyString('path');
        return $validator;
    }
}
