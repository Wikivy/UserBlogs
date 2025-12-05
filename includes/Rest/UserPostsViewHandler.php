<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use Wikivy\UserBlogs\Services\BlogService;
use Wikimedia\ParamValidator\ParamValidator;

class UserPostsViewHandler extends SimpleHandler
{

	public function __construct(
		private readonly BlogService $blogService
	) {
	}

	public function getParamSettings() {
		return [
			'username' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 20
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 0
			]
		];
	}

	public function run(): ResponseInterface {
		$p = $this->getValidatedParams();
		$username = $p['username'];
		$limit = max( 1, (int)$p['limit'] );
		$offset = max( 0, (int)$p['offset'] );

		$posts = $this->blogService->getUserPosts( $username, $limit, $offset );

		$items = [];
		foreach ( $posts as $post ) {
			$title = $post['title'];
			$items[] = [
				'pageId'       => $post['pageId'],
				'title'        => $title->getPrefixedText(),
				'slug'         => $title->getSubpageText(),
				'url'          => $title->getFullURL(),
				'username'     => $post['username'],
				'userId'       => $post['userId'],
				'created'      => $post['created'],
				'lastTouched'  => $post['lastTouched'],
				'commentCount' => $post['commentCount'],
				'lastComment'  => $post['lastComment'],
				'lastCommentUser' => $post['lastCommentUser'],
			];
		}

		return $this->getResponseFactory()->createJson( [
			'username' => $username,
			'limit'    => $limit,
			'offset'   => $offset,
			'posts'    => $items
		] );
	}

}
