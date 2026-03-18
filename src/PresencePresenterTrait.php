<?php

declare(strict_types=1);

namespace NPresence;

/**
 * Trait for Nette presenters — adds heartbeat endpoint.
 *
 * Usage:
 *   use PresencePresenterTrait;
 *   // Then whitelist 'heartbeat' in startup()
 */
trait PresencePresenterTrait
{
	private PresenceService $presenceService;


	public function injectPresenceService(PresenceService $presenceService): void
	{
		$this->presenceService = $presenceService;
	}


	/**
	 * AJAX heartbeat endpoint — called by JS every 30 seconds.
	 */
	public function actionHeartbeat(): void
	{
		$user = $this->getUser();
		if (!$user->isLoggedIn()) {
			$this->sendJson(['status' => 'unauthorized']);
			return;
		}

		$identity = $user->getIdentity();

		$this->presenceService->heartbeat([
			'user_id' => (int) $user->getId(),
			'session_id' => $this->getSession()->getId(),
			'email' => $identity->email ?? '',
			'full_name' => $identity->fullName ?? '',
			'role' => $identity->roleSlug ?? '',
			'ip' => $this->getHttpRequest()->getRemoteAddress() ?? '0.0.0.0',
			'user_agent' => $this->getHttpRequest()->getHeader('User-Agent') ?? '',
			'page' => $this->getHttpRequest()->getPost('page') ?? '',
			'title' => $this->getHttpRequest()->getPost('title') ?? '',
		]);

		$this->sendJson(['status' => 'ok']);
	}
}
