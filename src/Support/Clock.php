<?php

declare(strict_types=1);

namespace VC\Nexudus\Support;

final class Clock {
	public function now(): int {
		return time();
	}
}
