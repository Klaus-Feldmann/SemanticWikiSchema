<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Value;

enum ValueType: string {

	case String = 'string';
	case Url = 'url';
	case Number = 'number';
	case Boolean = 'boolean';
	case Relation = 'relation';
	case MonolingualText = 'monolingualText';

	/**
	 * The Value's Property Type is not registered, so its structure is unknown.
	 *
	 * @see UnregisteredTypeValue
	 */
	case UnregisteredType = 'unregisteredType';

}
