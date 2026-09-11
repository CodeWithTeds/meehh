<?php

declare(strict_types=1);

namespace Goat\Tests\Feature;

use Orchestra\Testbench\TestCase as BaseTestCase;

final class BoostSkillTest extends BaseTestCase
{
    public function test_goat_skill_has_valid_frontmatter(): void
    {
        $skillDirectory = dirname(__DIR__, 2).'/resources/boost/skills/laravel-goat-development';
        $skillFile = $skillDirectory.'/SKILL.md';

        $this->assertFileExists($skillFile);

        $contents = file_get_contents($skillFile);

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression(
            '/\A---\Rname: laravel-goat-development\Rdescription: .+\R---\R/s',
            $contents,
        );
        $this->assertStringContainsString('meehh/laravel-goat', $contents);
    }

    public function test_goat_skill_documents_safe_generation_boundaries(): void
    {
        $skillFile = dirname(__DIR__, 2).'/resources/boost/skills/laravel-goat-development/SKILL.md';
        $contents = file_get_contents($skillFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('--except=migration', $contents);
        $this->assertStringContainsString('Do not combine `--only` and `--except`', $contents);
        $this->assertStringContainsString('Treat `--force` as destructive', $contents);
        $this->assertStringContainsString('routes and factories', $contents);
        $this->assertStringContainsString('authorization rules', $contents);
        $this->assertStringContainsString('domain validation', $contents);
        $this->assertStringContainsString('test suite', $contents);
    }

    public function test_goat_skill_defines_schema_gathering_and_execution_workflow(): void
    {
        $skillFile = dirname(__DIR__, 2).'/resources/boost/skills/laravel-goat-development/SKILL.md';
        $contents = file_get_contents($skillFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('ask focused questions', $contents);
        $this->assertStringContainsString("In planning mode, show the proposed input and command but do not run `goat:make`", $contents);
        $this->assertStringContainsString('Do not ask the user to paste schema manually into a terminal', $contents);
        $this->assertStringContainsString('Do not invent business-critical columns or constraints', $contents);
        $this->assertStringContainsString('should not run a bare interactive `php artisan goat:make Product` command', $contents);
        $this->assertStringContainsString('explicit STDIN heredoc', $contents);
    }

    public function test_goat_skill_preserves_the_full_architecture_by_default(): void
    {
        $skillFile = dirname(__DIR__, 2).'/resources/boost/skills/laravel-goat-development/SKILL.md';
        $contents = file_get_contents($skillFile);

        $this->assertIsString($contents);
        $this->assertStringContainsString('A default run generates the complete feature slice', $contents);
        $this->assertStringContainsString('do not silently remove them', $contents);
        $this->assertStringContainsString('preserve the full component set', $contents);
        $this->assertStringContainsString('do not replace GOAT\'s components with unrelated Laravel generators', $contents);
        $this->assertStringContainsString('When the user asks for the complete GOAT architecture, omit both flags', $contents);
    }
}
