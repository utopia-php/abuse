<?php

declare(strict_types=1);

namespace Utopia\Tests;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DiscoveryTest extends TestCase
{
    public function testConfiguredDiscoveryIncludesBackendAndLifecycleCases(): void
    {
        $root = dirname(__DIR__, 2);
        $listing = '';
        $log = '';
        $process = null;

        try {
            $listing = tempnam(sys_get_temp_dir(), 'abuse-discovery-') ?: throw new RuntimeException('Cannot create discovery listing');
            $log = tempnam(sys_get_temp_dir(), 'abuse-discovery-') ?: throw new RuntimeException('Cannot create discovery log');
            $process = proc_open(
                [PHP_BINARY, $root . '/vendor/bin/phpunit', '--configuration', $root . '/phpunit.xml', '--list-tests-xml', $listing],
                [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
                $pipes,
                $root,
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $status = proc_close($process);
            $process = null;
            $this->assertSame(0, $status, (string) file_get_contents($log));

            $document = new DOMDocument();
            $this->assertTrue($document->load($listing));
            $cases = [];
            foreach ($document->getElementsByTagName('testCaseClass') as $class) {
                $methods = [];
                foreach ($class->getElementsByTagName('testCaseMethod') as $method) {
                    $methods[] = $method->getAttribute('name');
                }
                sort($methods);
                $cases[$class->getAttribute('name')] = $methods;
            }

            $this->assertArrayHasKey(AppwriteTablesDBTest::class, $cases);
            $this->assertSame([
                'testDynamicKey',
                'testDynamicKeyFastRequests',
                'testDynamicKeyWith2Params',
                'testLimitReset',
                'testReset',
                'testSetupCreatesSchema',
                'testSetupIsIdempotent',
                'testSetupRepairsPartiallyCreatedTable',
                'testStaticKey',
                'testTimeFormat',
            ], $cases[AppwriteTablesDBTest::class]);
            $this->assertArrayHasKey(TablesDBFixtureTest::class, $cases);
            $this->assertCount(8, $cases[TablesDBFixtureTest::class]);
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            foreach ([$listing, $log] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
