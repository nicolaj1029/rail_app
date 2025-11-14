<?php
use Migrations\AbstractMigration;

class CreateCases extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('cases');
        $table
            ->addColumn('ref', 'string', ['limit' => 36, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'open'])
            ->addColumn('travel_date', 'date', ['null' => true])
            ->addColumn('passenger_name', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('operator', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('country', 'string', ['limit' => 4, 'null' => true])
            ->addColumn('operator_code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('delay_min_eu', 'integer', ['null' => true])
            ->addColumn('remedy_choice', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('art20_expenses_total', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('comp_band', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('comp_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true])
            ->addColumn('currency', 'string', ['limit' => 8, 'null' => true])
            ->addColumn('eu_only', 'boolean', ['default' => true])
            ->addColumn('extraordinary', 'boolean', ['default' => false])
            ->addColumn('duplicate_flag', 'boolean', ['default' => false])
            ->addColumn('assigned_to', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('due_at', 'datetime', ['null' => true])
            ->addColumn('attachments_count', 'integer', ['default' => 0])
            ->addColumn('flow_snapshot', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addColumn('modified', 'datetime')
            ->addIndex(['status'])
            ->addIndex(['ref'])
            ->create();
    }
}
