<?php

namespace Wikivy\UserBlogs\Api;

use ApiBase;

class ApiBlogComment extends ApiBase {
	
	public function execute() {
		$user = $this->getUser();
		
		if ( !$user->isLoggedIn() ) {
			$this->dieWithError( 'userblogs-must-login-comment', 'notloggedin' );
		}

		$params = $this->extractRequestParams();
		$action = $params['commentaction'];

		switch ( $action ) {
			case 'add':
				$this->addComment( $params, $user );
				break;
			case 'delete':
				$this->deleteComment( $params, $user );
				break;
			default:
				$this->dieWithError( 'Invalid action', 'invalidaction' );
		}
	}

	private function addComment( $params, $user ) {
		$pageId = $params['pageid'];
		$content = trim( $params['content'] );
		$parentId = $params['parentid'];

		if ( empty( $content ) ) {
			$this->dieWithError( 'Comment cannot be empty', 'emptycontent' );
		}

		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->insert(
			'user_blog_comments',
			[
				'ubc_page_id' => $pageId,
				'ubc_user_id' => $user->getId(),
				'ubc_parent_id' => $parentId,
				'ubc_content' => $content,
				'ubc_timestamp' => $dbw->timestamp()
			],
			__METHOD__
		);

		$commentId = $dbw->insertId();

		// Get the new comment data
		$row = $dbw->selectRow(
			'user_blog_comments',
			[ 'ubc_id', 'ubc_user_id', 'ubc_parent_id', 'ubc_content', 'ubc_timestamp' ],
			[ 'ubc_id' => $commentId ],
			__METHOD__
		);

		$this->getResult()->addValue( null, 'success', true );
		$this->getResult()->addValue( null, 'comment', [
			'id' => $row->ubc_id,
			'user_id' => $row->ubc_user_id,
			'user_name' => $user->getName(),
			'parent_id' => $row->ubc_parent_id,
			'content' => $row->ubc_content,
			'timestamp' => wfTimestamp( TS_RFC2822, $row->ubc_timestamp )
		] );
	}

	private function deleteComment( $params, $user ) {
		$commentId = $params['commentid'];

		$dbr = wfGetDB( DB_REPLICA );
		$comment = $dbr->selectRow(
			'user_blog_comments',
			[ 'ubc_user_id' ],
			[ 'ubc_id' => $commentId ],
			__METHOD__
		);

		if ( !$comment ) {
			$this->dieWithError( 'Comment not found', 'notfound' );
		}

		// Check permissions
		if ( $comment->ubc_user_id != $user->getId() && !$user->isAllowed( 'delete' ) ) {
			$this->dieWithError( 'You do not have permission to delete this comment', 'nopermission' );
		}

		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->delete(
			'user_blog_comments',
			[ 'ubc_id' => $commentId ],
			__METHOD__
		);

		$this->getResult()->addValue( null, 'success', true );
		$this->getResult()->addValue( null, 'commentid', $commentId );
	}

	public function getAllowedParams() {
		return [
			'commentaction' => [
				ApiBase::PARAM_TYPE => [ 'add', 'delete' ],
				ApiBase::PARAM_REQUIRED => true,
			],
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
			'content' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'parentid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DFLT => 0,
			],
			'commentid' => [
				ApiBase::PARAM_TYPE => 'integer',
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		return true;
	}
}
