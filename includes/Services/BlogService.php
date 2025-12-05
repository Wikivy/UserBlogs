<?php

namespace Wikivy\UserBlogs\Services;

use MediaWiki\Config\Config;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use WikiMedia\Rdbms\ILBFactory;
use MediaWiki\HookContainer\HookContainer;

class BlogService
{
	public function __construct(
		private readonly ILBFactory $lbFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserFactory $userFactory,
		private readonly Config $config,
		private readonly HookContainer $hookContainer
	) {
	}

	/* ---------- POST METADATA ---------- */

	/**
	 * Ensure there's a metadata row for this blog post page.
	 * Call this when a User_blog: page is first created/saved.
	 */
	public function registerPost( Title $postTitle, UserIdentity $author ): void
	{
		if (!$postTitle->inNamespace(NS_USER_BLOG)) {
			return;
		}

		$dbw = $this->lbFactory->getPrimaryDatabase();
		$pageId = $postTitle->getArticleID();
		if (!$pageId) {
			return;
		}

		$ts = $dbw->timestamp();

		// If already exists, just update last_touched
		$row = $dbw->selectRow(
			'userblogs_posts',
			[ 'ubp_page_id' ],
			[ 'ubp_page_id' => $pageId ],
			__METHOD__
		);

		if ($row) {
			$dbw->update(
				'userblogs_posts',
				[ 'ubp_last_touched' => $ts ],
				[ 'ubp_page_id' => $pageId ],
				__METHOD__
			);

			return;
		}

		$dbw->insert(
			'userblogs_posts',
			[
				'ubp_page_id' => $pageId,
				'ubp_user_id' => $author->getId(),
				'ubp_username' => $author->getName(),
				'ubp_created' => $ts,
				'ubp_last_touched' => $ts,
				'ubp_last_comment' => null,
				'ubp_last_comment_user' => null,
			],
			__METHOD__
		);
	}

	/**
	 * Recent posts for Blog:Recent_posts.
	 *
	 * @return array[]
	 */
	public function getRecentPosts(int $limit = 20, int $offset = 0): array
	{
		$dbr = $this->lbFactory->getReplicaDatabase();
		$res = $dbr->select(
			'userblogs_posts',
			[
				'ubp_page_id',
				'ubp_user_id',
				'ubp_username',
				'ubp_created',
				'ubp_last_touched',
				'ubp_comment_count',
				'ubp_last_comment',
				'ubp_last_comment_user'
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'ubp_created DESC',
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);

		$posts = [];
		foreach ($res as $row) {
			$title = $this->titleFactory->newFromID($row->ubp_page_id);
			if (!$title) {
				continue;
			}

			$posts[] = [
				'pageId' => $row->ubp_page_id,
				'title' => $title,
				'userId' => $row->ubp_user_id,
				'username' => $row->ubp_username,
				'created' => $row->ubp_created,
				'lastTouched' => $row->ubp_last_touched,
				'commentCount' => $row->ubp_comment_count,
				'lastComment' => $row->ubp_last_comment,
				'lastCommentUser' => $row->ubp_last_comment_user !== null
					? $row->ubp_last_comment_user
					: null,
			];
		}


		return $posts;
	}

	/**
	 * Posts for a specific user for User_blog:Username.
	 *
	 * @return array[]
	 */
	public function getUserPosts(
		string $username,
		int $limit = 20,
		int $offset = 0
	): array {
		$dbr = $this->lbFactory->getReplicaDatabase();

		$res = $dbr->select(
			'userblogs_posts',
			[
				'ubp_page_id',
				'ubp_user_id',
				'ubp_username',
				'ubp_created',
				'ubp_last_touched',
				'ubp_comment_count',
				'ubp_last_comment',
				'ubp_last_comment_user'
			],
			[ 'ubp_username' => $username ],
			__METHOD__,
			[
				'ORDER BY' => 'ubp_created DESC',
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);

		$posts = [];
		foreach ($res as $row) {
			$title = $this->titleFactory->newFromID($row->ubp_page_id);
			if ( !$title ) {
				continue;
			}
			$posts[] = [
				'pageId' => $row->ubp_page_id,
				'title' => $title,
				'userId' => $row->ubp_user_id,
				'username' => $row->ubp_username,
				'created' => $row->ubp_created,
				'lastTouched' => $row->ubp_last_touched,
				'commentCount' => $row->ubp_comment_count,
				'lastComment' => $row->ubp_last_comment,
				'lastCommentUser' => $row->ubp_last_comment_user !== null
					? $row->ubp_last_comment_user
					: null,
			];
		}

		return $posts;
	}

	/* ---------- COMMENTS ---------- */

	public function addComment(
		Title $postTitle,
		UserIdentity $author,
		string $content,
		?int $parentId = null): int {

		$dbw = $this->lbFactory->getPrimaryDatabase();
		$pageId = $postTitle->getArticleID();
		if (!$pageId) {
			return 0;
		}

		$ts = $dbw->timestamp();

		$dbw->insert(
			'userblogs_comments',
			[
				'ubc_post_page_id' => $pageId,
				'ubc_parent_id' => $parentId,
				'ubc_author' => $author->getId(),
				'ubc_created' => $ts,
				'ubc_content' => $content,
			],
			__METHOD__
		);
		$commentId = $dbw->insertId();

		$dbw->update(
			'userblogs_posts',
			[
				'ubp_comment_count = ubp_comment_count + 1',
				'ubp_last_comment' => $ts,
				'ubp_last_comment_user' => $author->getId(),
				'ubp_last_touched' => $ts,
			],
			['ubp_page_id' => $pageId],
			__METHOD__
		);

		// Fire hook for notifications, extensions, etc.
		$this->hookContainer->run(
			'UserBlogsAfterCommentAdded',
			[ $postTitle, $commentId, $author, $parentId ]
		);

		return $commentId;
	}

	/**
	 * Get comments for a post (flat list with parent references).
	 *
	 * @return array[]
	 */
	public function getCommentsForPost(
		Title $postTitle,
		int $limit = 50,
		int $offset = 0
	): array {
		$dbr = $this->lbFactory->getReplicaDatabase();
		$pageId = $postTitle->getArticleID();
		if ( !$pageId ) {
			return [];
		}

		// Get post author id so we can know whose "favorite" matters
		$postRow = $dbr->selectRow(
			'userblogs_posts',
			[ 'ubp_user_id' ],
			[ 'ubp_page_id' => $pageId ],
			__METHOD__
		);
		$postAuthorId = $postRow ? $postRow->ubp_user_id : 0;

		$res = $dbr->select(
			'userblogs_comments',
			[
				'ubc_id',
				'ubc_parent_id',
				'ubc_author',
				'ubc_created',
				'ubc_last_edited',
				'ubc_last_edited_user',
				'ubc_edit_count',
				'ubc_content'
			],
			[ 'ubc_post_page_id' => $pageId ],
			__METHOD__,
			[
				'ORDER BY' => 'ubc_created ASC, ubc_id ASC',
				'LIMIT' => $limit,
				'OFFSET' => $offset
			]
		);

		$commentIds = [];
		$comments = [];
		foreach ( $res as $row ) {
			$id = $row->ubc_id;
			$commentIds[] = $id;

			$comments[$id] = [
				'id' => $row->ubc_id,
				'parentId' => $row->ubc_parent_id !== null ? $row->ubc_parent_id : null,
				'author' => $row->ubc_author,
				'created' => $row->ubc_created,
				'lastEdited' => $row->ubc_last_edited,
				'lastEditedUser' => $row->ubc_last_edited_user !== null
					? $row->ubc_last_edited_user
					: null,
				'editCount' => $row->ubc_edit_count,
				'content' => $row->ubc_content,
				'likes'      => 0,
				'favoritedByPostAuthor' => false,
			];
		}

		if (!$commentIds) {
			return [];
		}

		// Likes
		$likeRes = $dbr->select(
			'userblogs_comment_likes',
			[ 'ubl_comment_id', 'COUNT(*) AS cnt' ],
			[ 'ubl_comment_id' => $commentIds ],
			__METHOD__,
			[ 'GROUP BY' => 'ubl_comment_id' ]
		);

		foreach ( $likeRes as $row ) {
			$cid = (int)$row->ubl_comment_id;
			if ( isset( $comments[ $cid ] ) ) {
				$comments[ $cid ]['likes'] = (int)$row->cnt;
			}
		}

		// Favorites (by post author)
		if ( $postAuthorId ) {
			$favRes = $dbr->select(
				'userblogs_comment_favorites',
				[ 'ubf_comment_id' ],
				[
					'ubf_comment_id' => $commentIds,
					'ubf_user_id'    => $postAuthorId
				],
				__METHOD__
			);
			foreach ( $favRes as $row ) {
				$cid = (int)$row->ubf_comment_id;
				if ( isset( $comments[ $cid ] ) ) {
					$comments[ $cid ]['favoritedByPostAuthor'] = true;
				}
			}
		}

		return array_values( $comments );
	}

	public function setCommentLike(
		int $commentId,
		UserIdentity $user,
		bool $like
	): bool {
		$dbw = $this->lbFactory->getPrimaryDatabase();
		$ts = $dbw->timestamp();

		if ( $like ) {
			$dbw->upsert(
				'userblogs_comment_likes',
				[
					'ubl_comment_id' => $commentId,
					'ubl_user_id'    => $user->getId(),
					'ubl_created'    => $ts
				],
				[ [ 'ubl_comment_id', 'ubl_user_id' ] ],
				[ 'ubl_created' => $ts ],
				__METHOD__
			);
		} else {
			$dbw->delete(
				'userblogs_comment_likes',
				[
					'ubl_comment_id' => $commentId,
					'ubl_user_id'    => $user->getId()
				],
				__METHOD__
			);
		}

		return true;
	}

	private function getCommentAndPostInfo( int $commentId ): ?array {
		$dbr = $this->lbFactory->getReplicaDatabase();
		$row = $dbr->selectRow(
			[ 'userblogs_comments', 'userblogs_posts' ],
			[
				'ubc_post_page_id',
				'ubc_author',
				'ubp_user_id'
			],
			[ 'ubc_id' => $commentId ],
			__METHOD__,
			[],
			[
				'userblogs_posts' => [
					'JOIN',
					'ubp_page_id = ubc_post_page_id'
				]
			]
		);

		if ( !$row ) {
			return null;
		}

		return [
			'postPageId'   => (int)$row->ubc_post_page_id,
			'commentAuthor'=> (int)$row->ubc_author,
			'postAuthor'   => (int)$row->ubp_user_id,
		];
	}

	public function setCommentFavorite(
		int $commentId,
		UserIdentity $user,
		bool $favorite
	): bool {
		$info = $this->getCommentAndPostInfo( $commentId );
		if ( !$info ) {
			return false;
		}

		// Only post author can favorite/unfavorite
		if ( $info['postAuthor'] !== $user->getId() ) {
			return false;
		}

		$dbw = $this->lbFactory->getPrimaryDatabase();
		$ts = $dbw->timestamp();

		if ( $favorite ) {
			$dbw->upsert(
				'userblogs_comment_favorites',
				[
					'ubf_comment_id' => $commentId,
					'ubf_user_id'    => $user->getId(),
					'ubf_created'    => $ts
				],
				[ [ 'ubf_comment_id', 'ubf_user_id' ] ],
				[ 'ubf_created' => $ts ],
				__METHOD__
			);
		} else {
			$dbw->delete(
				'userblogs_comment_favorites',
				[
					'ubf_comment_id' => $commentId,
					'ubf_user_id'    => $user->getId()
				],
				__METHOD__
			);
		}

		return true;
	}

	public function getPostsByCategory(
		string $categoryName,
		int $limit = 20,
		int $offset = 0
	): array {
		$dbr = $this->lbFactory->getReplicaDatabase();

		// Normalize to a DB key for cl_to
		$categoryTitle = $this->titleFactory->newFromText( $categoryName, NS_CATEGORY );
		if ( !$categoryTitle ) {
			return [];
		}
		$categoryKey = $categoryTitle->getDBkey();

		$res = $dbr->select(
			[ 'userblogs_posts', 'page', 'categorylinks' ],
			[
				'ubp_page_id',
				'ubp_user_id',
				'ubp_username',
				'ubp_created',
				'ubp_last_touched',
				'ubp_comment_count',
				'ubp_last_comment',
				'ubp_last_comment_user'
			],
			[
				'cl_to' => $categoryKey,
				'page_namespace' => NS_USER_BLOG
			],
			__METHOD__,
			[
				'ORDER BY' => 'ubp_created DESC',
				'LIMIT'    => $limit,
				'OFFSET'   => $offset
			],
			[
				'page' => [ 'JOIN', 'page_id = ubp_page_id' ],
				'categorylinks' => [ 'JOIN', 'cl_from = page_id' ]
			]
		);

		$posts = [];
		foreach ( $res as $row ) {
			$title = $this->titleFactory->newFromID( (int)$row->ubp_page_id );
			if ( !$title ) {
				continue;
			}
			$posts[] = [
				'pageId'           => (int)$row->ubp_page_id,
				'title'            => $title,
				'userId'           => (int)$row->ubp_user_id,
				'username'         => (string)$row->ubp_username,
				'created'          => (string)$row->ubp_created,
				'lastTouched'      => (string)$row->ubp_last_touched,
				'commentCount'     => (int)$row->ubp_comment_count,
				'lastComment'      => $row->ubp_last_comment,
				'lastCommentUser'  => $row->ubp_last_comment_user !== null
					? (int)$row->ubp_last_comment_user
					: null,
			];
		}

		return $posts;
	}
}
