<?php

declare(strict_types=1);

namespace VC\Nexudus\Api;

final class Endpoints {
	/**
	 * NOTE: Nexudus endpoint paths are configurable because docs were not directly verifiable from this environment.
	 */
	public const DEFAULT_MEMBERSHIPS_PATH = '/spaces/memberships';
	public const DEFAULT_ROOM_BOOKINGS_PATH = '/spaces/rooms';

	public static function normalize_base_url(string $url): string {
		$url = trim($url);
		if ('' === $url) {
			return '';
		}

		return rtrim($url, '/');
	}
}
