<?php

declare(strict_types=1);

namespace NPresence\Tests;

use NPresence\PresenceService;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../vendor/autoload.php';

\Tester\Environment::setup();

/**
 * Tests for PresenceService — unit tests with mock connection.
 */
class PresenceServiceTest extends TestCase
{
	public function testFormatAgoPraveTeď(): void
	{
		// Test via reflection since formatAgo is private
		$service = $this->createMockService();
		$method = new \ReflectionMethod($service, 'formatAgo');
		$method->setAccessible(true);

		Assert::same('právě teď', $method->invoke($service, 30));
		Assert::same('1 min', $method->invoke($service, 60));
		Assert::same('5 min', $method->invoke($service, 300));
		Assert::same('1 hod', $method->invoke($service, 3600));
		Assert::same('1 dní', $method->invoke($service, 86400));
	}


	public function testParseBrowser(): void
	{
		$service = $this->createMockService();
		$method = new \ReflectionMethod($service, 'parseBrowser');
		$method->setAccessible(true);

		Assert::same('Chrome', $method->invoke($service, 'Mozilla/5.0 Chrome/120'));
		Assert::same('Firefox', $method->invoke($service, 'Mozilla/5.0 Firefox/120'));
		Assert::same('Edge', $method->invoke($service, 'Mozilla/5.0 Edg/120'));
		Assert::same('Safari', $method->invoke($service, 'Mozilla/5.0 Safari/120'));
		Assert::same('curl', $method->invoke($service, 'curl/7.0'));
		Assert::same('Other', $method->invoke($service, 'unknown-agent'));
	}


	public function testFormatDuration(): void
	{
		$service = $this->createMockService();
		$method = new \ReflectionMethod($service, 'formatDuration');
		$method->setAccessible(true);

		Assert::same('30s', $method->invoke($service, 30));
		Assert::same('5 min', $method->invoke($service, 300));
		Assert::same('1 hod', $method->invoke($service, 3600));
	}


	private function createMockService(): PresenceService
	{
		$conn = \Mockery::mock(\Doctrine\DBAL\Connection::class);
		return new PresenceService($conn);
	}
}

// Only run if Mockery is available
if (class_exists(\Mockery::class)) {
	(new PresenceServiceTest())->run();
} else {
	// Fallback — test without Mockery using basic instantiation test
	echo "Skipping PresenceServiceTest (Mockery not available)\n";
}
