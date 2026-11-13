<?php
/** Include at the end of LocalSettings.php, after all SWSSparqlStores settings. */
declare( strict_types = 1 );

// Change only the local store's administrative name; retain endpoints and projections.
$swsLocalStores = $wgSWSSparqlStores ?? [];
if ( !is_array( $swsLocalStores ) ) {
    throw new RuntimeException( 'SWSSparqlStores must be an array.' );
}
$swsOldCount = 0;
$swsNewCount = 0;
foreach ( $swsLocalStores as $swsStore ) {
    if ( is_array( $swsStore ) ) {
        $swsStoreName = isset( $swsStore['name'] ) && is_string( $swsStore['name'] )
            ? trim( $swsStore['name'] ) : ( $swsStore['projection'] ?? 'native' );
        $swsOldCount += $swsStoreName === 'aewi-neowiki' ? 1 : 0;
        $swsNewCount += $swsStoreName === 'aewi' ? 1 : 0;
    }
}
if ( $swsOldCount > 1 || ( $swsOldCount > 0 && $swsNewCount > 0 ) ) {
    throw new RuntimeException( 'Store rename refused: aewi is already used or the old name is ambiguous.' );
}
foreach ( $swsLocalStores as &$swsStore ) {
    if ( is_array( $swsStore ) ) {
        $swsStoreName = isset( $swsStore['name'] ) && is_string( $swsStore['name'] )
            ? trim( $swsStore['name'] ) : ( $swsStore['projection'] ?? 'native' );
        if ( $swsStoreName === 'aewi-neowiki' ) {
            $swsStore['name'] = 'aewi';
        }
    }
}
unset( $swsStore );
$wgSWSSparqlStores = $swsLocalStores;
unset( $swsLocalStores, $swsOldCount, $swsNewCount, $swsStoreName );
