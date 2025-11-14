<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FormResolver;
use Cake\TestSuite\TestCase;

class FormResolverTest extends TestCase
{
    public function testEuFallbackWhenUnknownCountry(): void
    {
        $resolver = new FormResolver();
        $res = $resolver->decide(['country' => '']);
        $this->assertSame('eu_standard_claim', $res['form']);
    }

    public function testNationalPreferredFranceWhenFileMissingFallsBack(): void
    {
        $resolver = new FormResolver();
        $res = $resolver->decide(['country' => 'FR']);
        $this->assertSame('eu_standard_claim', $res['form']);
        $this->assertStringContainsString('prefers national', $res['reason']);
    }

    public function testNationalClaimWhenTemplateExists(): void
    {
        // Create a dummy FR template file temporarily in webroot/files
        $path = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'fr_g30.pdf';
        if (!is_dir(WWW_ROOT . 'files')) { mkdir(WWW_ROOT . 'files', 0777, true); }
        file_put_contents($path, '%PDF-FAKE');
        try {
            $resolver = new FormResolver();
            $res = $resolver->decide(['country' => 'FR']);
            $this->assertSame('national_claim', $res['form']);
            $this->assertNotEmpty($res['national']['path'] ?? '');
        } finally {
            @unlink($path);
        }
    }
}
