<?php

namespace Wikivy\UserBlogs;

use MediaWiki\MediaWikiServices;
use Wikivy\UserBlogs\Services\BlogService;

class UserBlogsServices
{
	/**
	 * @param MediaWikiServices|null $services
	 * @param string $name
	 *
	 * @return mixed
	 */
	private static function getService( ?MediaWikiServices $services, string $name ): mixed
	{
		if ( $services === null ) {
			$services = MediaWikiServices::getInstance();
		}

		return $services->getService( 'UserBlogs' . $name );
	}

	public static function getBlogsService( ?MediaWikiServices $services = null ): BlogService {
		return self::getService( $services, 'BlogService' );
	}
}
