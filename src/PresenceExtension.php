<?php

declare(strict_types=1);

namespace NPresence;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

/**
 * Nette DI extension for presence tracking.
 *
 * Configuration:
 *   extensions:
 *       presence: NPresence\PresenceExtension
 *
 *   presence:
 *       staleMinutes: 5   # after how many minutes is a user considered offline
 */
class PresenceExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'staleMinutes' => Expect::int(5),
		]);
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var \stdClass $config */
		$config = $this->getConfig();

		$builder->addDefinition($this->prefix('service'))
			->setFactory(PresenceService::class, [
				'staleMinutes' => $config->staleMinutes,
			]);
	}
}
