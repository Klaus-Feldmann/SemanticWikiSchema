<?php

declare( strict_types=1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Infrastructure\IdGenerator;

class IncrementalIdGenerator implements IdGenerator {

	private int $currentIndex;
	private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	public function __construct( int $startingIndex = 0 ) {
		$this->currentIndex = $startingIndex;
	}

	public function generate(): string {
		$id = 'zzzzzzzzzz' . $this->encodeIndex( $this->currentIndex );
		$this->currentIndex++;
		return $id;
	}

	private function encodeIndex( int $index ): string {
		$encoded = '';
		do {
			$encoded = self::BASE58_ALPHABET[$index % 58] . $encoded;
			$index = (int)( $index / 58 );
		} while ( $index > 0 );

		return str_pad( $encoded, 4, self::BASE58_ALPHABET[0], STR_PAD_LEFT );
	}

}
