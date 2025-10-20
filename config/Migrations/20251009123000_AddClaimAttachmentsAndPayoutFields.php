<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddClaimAttachmentsAndPayoutFields extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('claim_attachments')) {
            $this->table('claim_attachments')
                ->addColumn('claim_id', 'integer')
                ->addColumn('type', 'string', ['limit' => 32])
                ->addColumn('path', 'string', ['limit' => 255])
                ->addColumn('original_name', 'string', ['limit' => 255])
                ->addColumn('size', 'integer', ['default' => 0])
                ->addColumn('created', 'datetime')
                ->addIndex(['claim_id'])
                ->create();
        }

        if ($this->hasTable('claims')) {
            $this->table('claims')
                ->addColumn('payout_status', 'string', ['limit' => 32, 'default' => 'pending'])
                ->addColumn('payout_reference', 'string', ['limit' => 64, 'null' => true])
                ->addColumn('paid_at', 'datetime', ['null' => true])
                ->addColumn('notes', 'text', ['null' => true])
                ->update();
        }
    }
}
