<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Layout;

use ProfessionalWiki\SWS\Domain\Schema\SchemaName;

readonly class Layout {

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct(
		private LayoutName $name,
		private SchemaName|\ProfessionalWiki\SWS\Domain\Schema\SchemaReference $schema,
		private string $type,
		private string $description,
		private DisplayRules $displayRules,
		private array $settings,
	) {
	}

	public function getName(): LayoutName {
		return $this->name;
	}

	public function getSchema(): SchemaName {
		return $this->schema instanceof SchemaName ? $this->schema : $this->schema->name;
	}

    public function getSchemaReference(): \ProfessionalWiki\SWS\Domain\Schema\SchemaReference {
        return $this->schema instanceof SchemaName ? \ProfessionalWiki\SWS\Domain\Schema\SchemaReference::local($this->schema) : $this->schema;
    }

	public function getType(): string {
		return $this->type;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getDisplayRules(): DisplayRules {
		return $this->displayRules;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getSettings(): array {
		return $this->settings;
	}

}
