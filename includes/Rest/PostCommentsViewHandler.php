<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikivy\UserBlogs\Services\BlogService;

class PostCommentsViewHandler extends SimpleHandler
{
	public function __construct(
		private readonly BlogService $blogService
	) {
	}

	public function getParamSettings() {
		return [
			'postPageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 50
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
		$postPageId = (int)$p['postPageId'];
		$limit = max( 1, (int)$p['limit'] );
		$offset = max( 0, (int)$p['offset'] );

		$title = Title::newFromID( $postPageId );
		if ( !$title || !$title->inNamespace( NS_USER_BLOG ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'notfound', 'message' => 'Post not found' ],
				404
			);
		}

		$comments = $this->blogService->getCommentsForPost( $title, $limit, $offset );

		return $this->getResponseFactory()->createJson( [
			'post' => [
				'pageId' => $postPageId,
				'title'  => $title->getPrefixedText(),
				'url'    => $title->getFullURL(),
			],
			'comments'       => $comments,
			'commentsFormat' => 'wikitext',
			'limit'          => $limit,
			'offset'         => $offset
		] );
	}
}
