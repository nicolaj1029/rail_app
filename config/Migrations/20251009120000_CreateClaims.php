<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateClaims extends AbstractMigration
{
    public function change(): void
    {
        $this->table('claims')
            ->addColumn('case_number', 'string', ['limit' => 32])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'new'])
            ->addColumn('client_name', 'string', ['limit' => 200])
            ->addColumn('client_email', 'string', ['limit' => 200])
            ->addColumn('country', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('operator', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('product', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('delay_min', 'integer', ['default' => 0])
            ->addColumn('refund_already', 'boolean', ['default' => false])
            ->addColumn('known_delay_before_purchase', 'boolean', ['default' => false])
            ->addColumn('extraordinary', 'boolean', ['default' => false])
            ->addColumn('self_inflicted', 'boolean', ['default' => false])
            ->addColumn('ticket_price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'EUR'])
            ->addColumn('computed_percent', 'integer', ['default' => 0])
            ->addColumn('computed_source', 'string', ['limit' => 32, 'default' => 'eu'])
            ->addColumn('computed_notes', 'text', ['null' => true])
            ->addColumn('compensation_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('fee_percent', 'integer', ['default' => 25])
            ->addColumn('fee_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('payout_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0])
            ->addColumn('assignment_accepted', 'boolean', ['default' => false])
            ->addColumn('assignment_pdf', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('evidence_json', 'text', ['null' => true])
            ->addTimestamps()
            ->addIndex(['case_number'], ['unique' => true])
            ->create();
    }
}
