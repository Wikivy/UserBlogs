<?php

namespace Wikivy\UserBlogs;

use MediaWiki\MediaWikiServices;
use Wikivy\UserBlogs\Services\BlogService;


return [
	'UserBlogsBlogService' => static function (MediaWikiServices $services): BlogService {
		return new BlogService(
			$services->getDBLoadBalancerFactory(),
			$services->getTitleFactory(),
			$services->getUserFactory(),
			$services->getMainConfig(),
			$services->getHookContainer()
		);
	}
];
