<?php

namespace Wikivy\UserBlogs\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Notification\NotificationService;
use MediaWiki\Notification\RecipientSet;
use MediaWiki\Notification\Types\WikiNotification;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\ILBFactory;
use Wikivy\UserBlogs\Hooks\UserBlogsAfterCommentAddedHook;

class NotificationHooks implements UserBlogsAfterCommentAddedHook
{

	public function __construct(
		private readonly ILBFactory $lbFactory,
		private readonly UserFactory $userFactory,
		private readonly NotificationService $notificationService,
		private readonly Config $config,
		private readonly TitleFactory $titleFactory
	) {
	}

	public function onUserBlogsAfterCommentAdded(
		Title $postTitle,
		int $commentId,
		UserIdentity $author,
		?int $parentCommentId) {

		// Notify post author (new comment)
		$postInfo = $this->getPostInfo( $postTitle );
		if ( $postInfo && $postInfo['authorId'] !== $author->getId() ) {
			$postAuthor = $this->userFactory->newFromId( $postInfo['authorId'] );
			if ( $postAuthor && $postAuthor->isRegistered() ) {
				$notification = new WikiNotification(
					'userblogs-comment-on-post',
					$postTitle,
					$author,
					[
						'post-page-id' => $postTitle->getArticleID(),
						'comment-id'   => $commentId
					]
				);
				$this->notificationService->notify(
					$notification,
					new RecipientSet( $postAuthor )
				);
			}
		}

		// Notify parent comment author (reply-to-comment)
		if ( $parentCommentId !== null ) {
			$parentInfo = $this->getCommentInfo( $parentCommentId );
			if ( $parentInfo && $parentInfo['authorId'] !== $author->getId() ) {
				$parentAuthor = $this->userFactory->newFromId( $parentInfo['authorId'] );
				if ( $parentAuthor && $parentAuthor->isRegistered() ) {
					$notification = new WikiNotification(
						'userblogs-reply-to-comment',
						$postTitle,
						$author,
						[
							'post-page-id'    => $postTitle->getArticleID(),
							'comment-id'      => $commentId,
							'parent-comment-id' => $parentCommentId
						]
					);
					$this->notificationService->notify(
						$notification,
						new RecipientSet( $parentAuthor )
					);
				}
			}
		}

	}

	private function getPostInfo( Title $postTitle ): ?array {
		$dbr = $this->lbFactory->getReplicaDatabase();
		$row = $dbr->selectRow(
			'userblogs_posts',
			[ 'ubp_user_id' ],
			[ 'ubp_page_id' => $postTitle->getArticleID() ],
			__METHOD__
		);
		if ( !$row ) {
			return null;
		}
		return [
			'authorId' => (int)$row->ubp_user_id
		];
	}

	private function getCommentInfo( int $commentId ): ?array {
		$dbr = $this->lbFactory->getReplicaDatabase();
		$row = $dbr->selectRow(
			'userblogs_comments',
			[ 'ubc_author', 'ubc_post_page_id' ],
			[ 'ubc_id' => $commentId ],
			__METHOD__
		);
		if ( !$row ) {
			return null;
		}

		return [
			'authorId'  => (int)$row->ubc_author,
			'postPageId'=> (int)$row->ubc_post_page_id,
		];
	}
}
