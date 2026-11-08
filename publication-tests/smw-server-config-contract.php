<?php
// Isolated host contract: the real reader must use main config and no Lua/title service.
namespace MediaWiki\Parser { class Parser {} class ParserOutput {} }
namespace MediaWiki\User { class User {} }
namespace MediaWiki {
    final class MediaWikiServices {
        public static array $settings = [];
        public static function getInstance(): self { return new self; }
        public function getMainConfig(): object { return new class {
            public function get(string $key): mixed {
                if ($key !== 'WikiSchemaSmwMapping') { throw new \RuntimeException('Unexpected setting'); }
                return MediaWikiServices::$settings;
            }
        }; }
        public function __call(string $name, array $args): never { throw new \RuntimeException('Unexpected wiki/Lua service '.$name); }
    }
}
namespace {
    require __DIR__.'/bootstrap.php';
    $reader = new \ProfessionalWiki\SWS\EntryPoints\Semantic\WikiAnnotationConfig();
    $args = [new \MediaWiki\Parser\Parser(), new \MediaWiki\Parser\ParserOutput(), new \MediaWiki\User\User()];
    $config = $reader->read(...$args);
    if ($config['properties'] !== [] || $config['urlEntries']['subject'] !== 'WikiSchema-Subjekt') { throw new \RuntimeException('Invalid defaults'); }
    \MediaWiki\MediaWikiServices::$settings = ['urlEntries'=>['field'=>'Feldkennung']];
    $config = $reader->read(...$args);
    if ($config['urlEntries']['field'] !== 'Feldkennung' || $config['urlEntries']['url'] !== 'Quellen-URL') { throw new \RuntimeException('Override failed'); }
    echo "Server configuration contract passed without wiki module or Scribunto engine access.\n";
}
