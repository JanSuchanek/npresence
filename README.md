# NPresence — Heartbeat & Session Tracking

User presence tracking for Nette applications — heartbeats, active sessions, activity logging, browser detection.

## Installation

```bash
composer require jansuchanek/npresence
```

## Nette Integration

```neon
extensions:
    presence: NPresence\PresenceExtension

presence:
    staleMinutes: 5  # optional, default 5
```

## Usage

### In Presenter (heartbeat endpoint)

```php
use NPresence\PresencePresenterTrait;

class SecurityPresenter extends BasePresenter
{
    use PresencePresenterTrait;
}
```

### Service API

```php
use NPresence\PresenceService;

$service->heartbeat($userId, $sessionId, $userAgent);
$activeSessions = $service->getActiveSessions();
$service->logActivity($userId, 'login', $request);
$service->cleanupStaleSessions();
```

## Requirements

- PHP >= 8.1
- doctrine/dbal
