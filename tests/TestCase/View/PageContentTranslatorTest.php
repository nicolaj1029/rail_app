<?php
declare(strict_types=1);

namespace App\Test\TestCase\View;

use App\View\PageContentTranslator;
use Cake\TestSuite\TestCase;

final class PageContentTranslatorTest extends TestCase
{
    public function testTranslateChangesVisibleTextOnly(): void
    {
        $html = <<<'HTML'
<section id="railTicketlessSection2" data-label="Ticketless">
    <h2>Ticketless</h2>
    <style>.railTicketlessSection2::after { content: "Ticketless"; }</style>
    <script>const railTicketlessSection2 = document.getElementById('railTicketlessSection2');</script>
</section>
HTML;

        $translated = PageContentTranslator::translate($html, ['Ticketless' => 'Sans billet']);

        $this->assertStringContainsString('<h2>Sans billet</h2>', $translated);
        $this->assertStringContainsString('id="railTicketlessSection2"', $translated);
        $this->assertStringContainsString('data-label="Ticketless"', $translated);
        $this->assertStringContainsString('content: "Ticketless"', $translated);
        $this->assertStringContainsString('const railTicketlessSection2', $translated);
        $this->assertStringNotContainsString('railSans billetSection2', $translated);
    }
}
