<?php

namespace App\Services\Playlist;

final class IpNormalizer
{
	public function normalize(string $ip): string
	{
		$ip = trim($ip);
		if ($ip === '') return '';

		$packed = @inet_pton($ip);
		if ($packed === false) return $ip;

		// ::ffff:a.b.c.d → a.b.c.d
		if (
			strlen($packed) === 16
			&& substr($packed, 0, 12) === str_repeat("\x00", 10) . "\xff\xff"
		) {
			$packed = substr($packed, 12);
		}

		return inet_ntop($packed);
	}

	/** @return string[] */
	public function variants(string $ip): array
	{
		$normalized = $this->normalize($ip);

		if ($normalized === '127.0.0.1' || $normalized === '::1') {
			return ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
		}

		return [$normalized];
	}

	public function isLocal(string $ip): bool
	{
		return in_array($this->normalize($ip), ['127.0.0.1', '::1'], true);
	}
}
