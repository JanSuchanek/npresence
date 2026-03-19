<?php

declare(strict_types=1);

namespace NPresence;

use Doctrine\DBAL\Connection;

/**
 * Presence tracking service — heartbeats, sessions, activity logging.
 *
 * Uses DBAL for database-agnostic operation.
 * Tables required: user_session, user_activity_log
 */
class PresenceService
{
	public function __construct(
		private Connection $connection,
		private int $staleMinutes = 5,
	) {}


	/**
	 * Record a heartbeat for a user session.
	 *
	 * @param array{user_id: int, session_id: string, email: string, full_name: string, role: string, ip: string, user_agent: string, page: string, title: string} $data
	 */
	public function heartbeat(array $data): void
	{
		$userId = $data['user_id'];
		$sessionId = $data['session_id'];

		// Upsert session
		$existing = $this->connection->fetchOne(
			"SELECT id FROM user_session WHERE user_id = ? AND session_id = ?",
			[$userId, $sessionId],
		);

		if ($existing) {
			$this->connection->executeStatement(
				"UPDATE user_session SET last_heartbeat = NOW(), current_page = ?, page_title = ?,
				 heartbeat_count = heartbeat_count + 1 WHERE id = ?",
				[$data['page'], $data['title'], $existing],
			);
		} else {
			$this->connection->executeStatement(
				"INSERT INTO user_session (user_id, email, full_name, role, ip_address, user_agent, session_id,
				 current_page, page_title, last_heartbeat, heartbeat_count, logged_in_at)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, NOW())",
				[
					$userId, $data['email'], $data['full_name'], $data['role'],
					$data['ip'], $data['user_agent'], $sessionId,
					$data['page'], $data['title'],
				],
			);
		}

		// Activity log — increment duration or create new entry
		$lastActivity = $this->connection->fetchAssociative(
			"SELECT id, page FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
			[$userId],
		);

		if ($lastActivity && $lastActivity['page'] === $data['page']) {
			$this->connection->executeStatement(
				"UPDATE user_activity_log SET duration = duration + 1 WHERE id = ?",
				[$lastActivity['id']],
			);
		} else {
			$this->connection->executeStatement(
				"INSERT INTO user_activity_log (user_id, email, full_name, page, page_title, ip_address, created_at, duration)
				 VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)",
				[$userId, $data['email'], $data['full_name'], $data['page'], $data['title'], $data['ip']],
			);
		}
	}


	/**
	 * Get all live sessions with their status (online/stale/ghost).
	 *
	 * @return list<array{id: int, user_id: int, full_name: string, email: string, current_page: string, status: string, last_heartbeat: string, browser: string, ago: string}>
	 */
	public function getLiveSessions(): array
	{
		$sessions = $this->connection->fetchAllAssociative(
			"SELECT * FROM user_session ORDER BY last_heartbeat DESC",
		);

		$now = time();
		foreach ($sessions as &$s) {
			$hb = strtotime($s['last_heartbeat']);
			$diff = $now - $hb;
			$s['status'] = $diff < 120 ? 'online' : ($diff < $this->staleMinutes * 60 ? 'away' : 'offline');
			$s['ago'] = $this->formatAgo($diff);
			$s['browser'] = $this->parseBrowser($s['user_agent'] ?? '');
			$s['duration'] = $this->formatDuration($now - strtotime($s['logged_in_at'] ?? $s['last_heartbeat']));
		}

		return $sessions;
	}


	/**
	 * Get online users (within stale threshold).
	 *
	 * @return list<array{user_id: int, full_name: string, email: string}>
	 */
	public function getOnlineUsers(): array
	{
		$since = (new \DateTime("-{$this->staleMinutes} minutes"))->format('Y-m-d H:i:s');

		return $this->connection->fetchAllAssociative(
			"SELECT DISTINCT user_id, full_name, email
			 FROM user_session WHERE last_heartbeat > ?",
			[$since],
		);
	}


	/**
	 * Get recent activity log.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function getRecentActivity(int $limit = 50): array
	{
		return $this->connection->fetchAllAssociative(
			"SELECT * FROM user_activity_log ORDER BY created_at DESC LIMIT {$limit}",
		);
	}


	/**
	 * Remove a session (kick user).
	 */
	public function kickSession(int $sessionId): void
	{
		$this->connection->executeStatement(
			"DELETE FROM user_session WHERE id = ?",
			[$sessionId],
		);
	}


	/**
	 * Clean stale sessions older than N hours.
	 */
	public function cleanStaleSessions(int $hoursOld = 24): int
	{
		$cutoff = (new \DateTime("-{$hoursOld} hours"))->format('Y-m-d H:i:s');

		return $this->connection->executeStatement(
			"DELETE FROM user_session WHERE last_heartbeat < ?",
			[$cutoff],
		);
	}


	private function formatAgo(int $seconds): string
	{
		if ($seconds < 60) return 'právě teď';
		if ($seconds < 3600) return round($seconds / 60) . ' min';
		if ($seconds < 86400) return round($seconds / 3600) . ' hod';
		return round($seconds / 86400) . ' dní';
	}


	private function formatDuration(int $seconds): string
	{
		if ($seconds < 60) return $seconds . 's';
		if ($seconds < 3600) return round($seconds / 60) . ' min';
		return round($seconds / 3600, 1) . ' hod';
	}


	private function parseBrowser(string $ua): string
	{
		if (str_contains($ua, 'Firefox')) return 'Firefox';
		if (str_contains($ua, 'Edg/')) return 'Edge';
		if (str_contains($ua, 'Chrome')) return 'Chrome';
		if (str_contains($ua, 'Safari')) return 'Safari';
		if (str_contains($ua, 'curl')) return 'curl';
		return 'Other';
	}
}
