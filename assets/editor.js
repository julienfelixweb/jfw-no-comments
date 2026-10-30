/**
 * Hides the comment blocks in the block editor.
 *
 * @package JFW\NoComments
 */

wp.domReady( function () {
	( window.jfwNoCommentsBlocks || [] ).forEach( function ( name ) {
		if ( wp.blocks.getBlockType( name ) ) {
			wp.blocks.unregisterBlockType( name );
		}
	} );
} );
