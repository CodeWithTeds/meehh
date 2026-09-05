<?php

declare(strict_types=1);

namespace Goat\Tests\Unit;

use Goat\Support\FileWriter;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class FileWriterTest extends TestCase
{
    private string $tmp;
    private FileWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/goat_test_' . uniqid();
        mkdir($this->tmp, 0777, true);
        $this->writer = new FileWriter(new Filesystem());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDir($this->tmp);
    }

    public function test_writes_new_file(): void
    {
        $path = $this->tmp . '/App/Models/Product.php';
        $res = $this->writer->write($path, '<?php class Product {}');
        $this->assertTrue($res['written']);
        $this->assertFalse($res['skipped']);
        $this->assertFileExists($path);
    }

    public function test_protects_existing_file_without_force(): void
    {
        $path = $this->tmp . '/App/Models/Product.php';
        $this->writer->write($path, '<?php // v1');
        $res = $this->writer->write($path, '<?php // v2', false);
        $this->assertFalse($res['written']);
        $this->assertTrue($res['skipped']);
        $this->assertStringContainsString('v1', file_get_contents($path));
    }

    public function test_overwrites_with_force(): void
    {
        $path = $this->tmp . '/App/Models/Product.php';
        $this->writer->write($path, '<?php // v1');
        $res = $this->writer->write($path, '<?php // v2', true);
        $this->assertTrue($res['written']);
        $this->assertStringContainsString('v2', file_get_contents($path));
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
