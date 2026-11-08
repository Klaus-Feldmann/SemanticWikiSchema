<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\WikiConfig;

/**
 * A source of the raw (undecoded-into-values, unvalidated) configuration from the on-wiki configuration
 * page. The production implementation reads MediaWiki:SWS lazily and memoizes it for the request.
 */
interface WikiConfigSource {

	/**
	 * The decoded top-level JSON object of the configuration page, or null when the page is absent,
	 * unreadable (e.g. the database is down), or not a JSON object.
	 *
	 * @return array<string, mixed>|null
	 */
	public function readConfig(): ?array;

}
