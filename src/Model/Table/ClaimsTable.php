<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ClaimsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('claims');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('ClaimAttachments')->setForeignKey('claim_id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('client_name')->notEmptyString('client_name')
            ->email('client_email')
            ->integer('delay_min')
            ->numeric('ticket_price');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['case_number'], 'Case number must be unique'));
        return $rules;
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, $options)
    {
        if ($entity->isNew() && empty($entity->case_number)) {
            $entity->case_number = $this->generateCaseNumber();
        }
    }

    private function generateCaseNumber(): string
    {
        // Simple yymmdd + random 4 chars
        return strtoupper(date('ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
