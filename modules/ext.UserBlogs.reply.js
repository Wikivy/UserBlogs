( function ( mw, $ ) {
	'use strict';

	function initReplyBehavior() {
		var $form = $( '#userblogs-comment-form' );
		var $parentInput = $( '#userblogs-comment-parent' );
		var $textarea = $form.find( 'textarea[name="userblogs-comment-content"]' );
		var $status = $( '#userblogs-comment-reply-status' );

		if ( !$form.length || !$parentInput.length || !$textarea.length ) {
			return;
		}

		function setReplyTarget( commentId ) {
			$parentInput.val( commentId );
			var $targetComment = $( '.userblogs-comment[data-comment-id="' + commentId + '"]' );

			var snippet = '';
			if ( $targetComment.length ) {
				// Optional: use first line of comment as context
				var text = $targetComment.text().trim().replace(/\s+/g, ' ');
				if ( text.length > 120 ) {
					text = text.slice( 0, 117 ) + '...';
				}
				snippet = text;
			}

			var html = mw.message( 'userblogs-comment-replying-to', commentId ).escaped();
			if ( snippet ) {
				html += ' ' + mw.message( 'userblogs-comment-replying-snippet' ).escaped() +
					' ' + mw.html.escape( snippet );
			}
			html += ' ' + '<a href="#" class="userblogs-comment-cancel-reply">' +
				mw.message( 'userblogs-comment-cancel-reply' ).escaped() + '</a>';

			$status.html( html );
			$status.prop( 'hidden', false );
		}

		function clearReplyTarget() {
			$parentInput.val( '0' );
			$status.prop( 'hidden', true );
			$status.empty();
		}

		// Delegate click on reply links
		$( document ).on( 'click', '.userblogs-comment-reply', function ( e ) {
			e.preventDefault();
			var commentId = $( this ).data( 'comment-id' );
			if ( !commentId ) {
				return;
			}

			setReplyTarget( commentId );

			// Scroll to form and focus
			var commentsAnchor = document.getElementById( 'userblogs-comments' );
			if ( commentsAnchor && commentsAnchor.scrollIntoView ) {
				commentsAnchor.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}

			$textarea.trigger( 'focus' );
		} );

		// Delegate click on "cancel reply"
		$( document ).on( 'click', '.userblogs-comment-cancel-reply', function ( e ) {
			e.preventDefault();
			clearReplyTarget();
		} );
	}

	mw.loader.using( [ 'mediawiki.util', 'mediawiki.jqueryMsg' ], function () {
		$( initReplyBehavior );
	} );

}( mediaWiki, jQuery ) );
