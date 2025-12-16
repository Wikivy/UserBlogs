<?php

namespace Wikivy\UserBlogs\Hooks\Handlers;

use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Installer implements LoadExtensionSchemaUpdatesHook
{
	public function onLoadExtensionSchemaUpdates($updater): void
	{
		wfDebugLog('UserBlogs Installer', __METHOD__);

		/** @var DatabaseUpdater $updater */
		$base = dirname( __DIR__ );
		$dir = "$base/../../sql";

		$type = $updater->getDB()->getType();

		$updater->addExtensionTable(
			'userblogs_posts',
			"$dir/$type/userblogs_posts.sql"
		);

		$updater->addExtensionTable(
			'userblogs_comments',
			"$dir/$type/userblogs_comments.sql"
		);

		$updater->addExtensionTable(
			'userblogs_comment_likes',
			"$dir/$type/userblogs_comment_likes.sql"
		);

		$updater->addExtensionTable(
			'userblogs_comment_favorites',
			"$dir/$type/userblogs_comment_favorites.sql"
		);

	}
}
