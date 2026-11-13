<?php
namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon {
    class LuaError extends \RuntimeException {}
    class LibraryBase {
        public function __construct(private mixed $parser) {}
        public function getParser(): mixed { return $this->parser; }
        public function getTitle(): mixed { return $this->parser->getTitle(); }
        public function incrementExpensiveFunctionCount(): void { $this->parser->incrementExpensiveFunctionCount(); }
        public function checkType(string $name,int $n,mixed $value,string $type): void { if (gettype($value)!==$type) { throw new LuaError('Wrong type'); } }
        public function getEngine(): mixed { return new class { public function registerInterface($file,$lib,$config): array { return $lib; } }; }
    }
}

namespace MediaWiki\User {
    class User implements \MediaWiki\Permissions\Authority {
        public function authorizeRead(string $action,mixed $title): bool { return (new \MediaWiki\Context\TestContext())->getAuthority()->authorizeRead($action,$title); }
    }
}
namespace ProfessionalWiki\SWS\EntryPoints\Semantic {
    // SMW handler boundary only: AutomaticAnnotations, the shared reader and both planners remain real.
    class SmwAnnotationApplier {
        public static mixed $plan;
        public function apply(mixed $title,mixed $output,mixed $plan): void { self::$plan=$plan; }
    }
}
