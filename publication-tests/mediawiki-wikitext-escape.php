<?php
// Test-only exact function excerpt from MediaWiki wmf/1.47.0-wmf.24 includes/GlobalFunctions.php.
// Copyright MediaWiki contributors; GPL-2.0-or-later. Production uses the installed core function.
// https://github.com/wikimedia/mediawiki/blob/wmf/1.47.0-wmf.24/includes/GlobalFunctions.php
$wgEnableMagicLinks ??= [];
$wgUrlProtocols ??= [ 'http://', 'https://', 'mailto:', 'tel:' ];
function wfEscapeWikiText( $input ): string {
	global $wgEnableMagicLinks;
	static $repl = null, $repl2 = null, $repl3 = null, $repl4 = null;
	if ( $repl === null || defined( 'MW_PHPUNIT_TEST' ) ) {
		// Tests depend upon being able to change $wgEnableMagicLinks, so don't cache
		// in those situations
		$repl = [
			'"' => '&#34;', '&' => '&#38;', "'" => '&#39;', '<' => '&#60;',
			'=' => '&#61;', '>' => '&#62;', '[' => '&#91;', ']' => '&#93;',
			'{' => '&#123;', '|' => '&#124;', '}' => '&#125;',
			';' => '&#59;', // a token inside language converter brackets
			'!!' => '&#33;!', // a token inside table context
			"\n!" => "\n&#33;", "\r!" => "\r&#33;", // a token inside table context
			"\n#" => "\n&#35;", "\r#" => "\r&#35;",
			"\n*" => "\n&#42;", "\r*" => "\r&#42;",
			"\n:" => "\n&#58;", "\r:" => "\r&#58;",
			"\n " => "\n&#32;", "\r " => "\r&#32;",
			"\n\n" => "\n&#10;", "\r\n" => "&#13;\n",
			"\n\r" => "\n&#13;", "\r\r" => "\r&#13;",
			"\n\t" => "\n&#9;", "\r\t" => "\r&#9;", // "\n\t\n" is treated like "\n\n"
			"\n----" => "\n&#45;---", "\r----" => "\r&#45;---",
			'__' => '_&#95;', '://' => '&#58;//',
			// Japanese magic words start w/ wide underscore
			'＿' => '&#xFF3F;',
			'~~~' => '~~&#126;', // protect from PST, just to be safe(r)
		];

		$magicLinks = array_keys( array_filter( $wgEnableMagicLinks ) );
		// We have to catch everything "\s" matches in PCRE
		foreach ( $magicLinks as $magic ) {
			$repl["$magic "] = "$magic&#32;";
			$repl["$magic\t"] = "$magic&#9;";
			$repl["$magic\r"] = "$magic&#13;";
			$repl["$magic\n"] = "$magic&#10;";
			$repl["$magic\f"] = "$magic&#12;";
		}
		// Additionally escape the following characters at the beginning of the
		// string, in case they merge to form tokens when spliced into a
		// string.  Tokens like -{ {{ [[ {| etc are already escaped because
		// the second character is escaped above, but the following tokens
		// are handled here: |+ |- __FOO__ ~~~
		// (Only single-byte characters can go here; multibyte characters
		// like 'wide underscore' must go into $repl above.)
		$repl3 = [
			'+' => '&#43;', '-' => '&#45;', '_' => '&#95;', '~' => '&#126;',
		];
		// Similarly, protect the following characters at the end of the
		// string, which could turn form the start of `__FOO__` or `~~~~`
		// A trailing newline could also form the unintended start of a
		// paragraph break if it is glued to a newline in the following
		// context.  Again, only single-byte characters can be protected
		// here; 'wide underscore' is protected by $repl above.
		$repl4 = [
			'_' => '&#95;', '~' => '&#126;',
			"\n" => "&#10;", "\r" => "&#13;",
			"\t" => "&#9;", // "\n\t\n" is treated like "\n\n"
		];

		// And handle protocols that don't use "://"
		global $wgUrlProtocols;
		$repl2 = [];
		foreach ( $wgUrlProtocols as $prot ) {
			if ( substr( $prot, -1 ) === ':' ) {
				$repl2[] = preg_quote( substr( $prot, 0, -1 ), '/' );
			}
		}
		$repl2 = $repl2 ? '/\b(' . implode( '|', $repl2 ) . '):/i' : '/^(?!)/';
	}
	// Tell phan that $repl2, $repl3 and $repl4 will also be non-null here
	'@phan-var string $repl2';
	'@phan-var string $repl3';
	'@phan-var string $repl4';
	// This will also stringify input in case it's not a string
	$text = substr( strtr( "\n$input", $repl ), 1 );
	if ( $text === '' ) {
		return $text;
	}
	$first = strtr( $text[0], $repl3 ); // protect first character
	if ( strlen( $text ) > 1 ) {
		$text = $first . substr( $text, 1, -1 ) .
		strtr( substr( $text, -1 ), $repl4 ); // protect last character
	} else {
		// special case for single-character strings
		$text = strtr( $first, $repl4 ); // protect last character
	}
	$text = preg_replace( $repl2, '$1&#58;', $text );
	return $text;
}
