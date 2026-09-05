<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Support\StubRenderer;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class StubRendererTest extends TestCase
{
    public function test_renders_model_stub(): void
    {
        $renderer = new StubRenderer(new Filesystem());
        $content = $renderer->render('model', [
            'namespace' => 'App\\Models',
            'class' => 'Product',
            'fillable' => "        'name',",
            'casts' => '',
            'relationships' => '',
            'relationshipsImports' => '',
            'softDeletesImport' => '',
            'softDeletesTrait' => '',
        ]);
        $this->assertStringContainsString('namespace App\\Models', $content);
        $this->assertStringContainsString('class Product', $content);
    }

    public function test_falls_back_to_package_stub(): void
    {
        $renderer = new StubRenderer(new Filesystem());
        $path = $renderer->resolvePath('model');
        $this->assertStringContainsString('stubs/model.stub', $path);
        $this->assertFileExists($path);
    }
}
