<?php

namespace Wikivy\UserBlogs\Hooks;

use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

interface UserBlogsAfterCommentAddedHook
{
	/**
	 * @param Title $postTitle
	 * @param int $commentId
	 * @param UserIdentity $author
	 * @param int|null $parentCommentId
	 */
	public function onUserBlogsAfterCommentAdded(
		Title $postTitle,
		int $commentId,
		UserIdentity $author,
		?int $parentCommentId
	);
}
