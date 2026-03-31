<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

final class AddRiskFieldsToCases extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('cases');
        $table
            ->addColumn('risk_score', 'integer', ['default' => 0, 'null' => false, 'after' => 'duplicate_flag'])
            ->addColumn('risk_level', 'string', ['limit' => 20, 'default' => 'low', 'null' => false, 'after' => 'risk_score'])
            ->addColumn('risk_flags', 'text', ['null' => true, 'after' => 'risk_level'])
            ->addColumn('fraud_review_required', 'boolean', ['default' => false, 'null' => false, 'after' => 'risk_flags'])
            ->addColumn('risk_last_evaluated_at', 'datetime', ['null' => true, 'after' => 'fraud_review_required'])
            ->addColumn('risk_summary', 'string', ['limit' => 255, 'null' => true, 'after' => 'risk_last_evaluated_at'])
            ->addIndex(['fraud_review_required'])
            ->addIndex(['risk_level'])
            ->update();
    }
}
