<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Title\Title;
use Wikivy\UserBlogs\Services\BlogService;
use Wikimedia\ParamValidator\ParamValidator;

class PostCommentCreateHandler extends SimpleHandler
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
			]
		];
	}

	public function run(): ResponseInterface {
		$params = $this->getValidatedParams();
		$postPageId = (int)$params['postPageId'];

		$title = Title::newFromID( $postPageId );
		if ( !$title || !$title->inNamespace( NS_USER_BLOG ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'notfound', 'message' => 'Post not found' ],
				404
			);
		}

		$auth = $this->getAuthority();
		if ( !$auth->isAllowed( 'userblogs-comment' ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'No permission to comment' ],
				403
			);
		}

		$request = $this->getRequest();
		$data = json_decode( $request->getBody()->getContents(), true ) ?: [];
		$content = $data['content'] ?? null;
		$parentId = isset( $data['parentId'] ) ? (int)$data['parentId'] : null;

		if ( $content === null || $content === '' ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'invalid', 'message' => 'Missing content' ],
				400
			);
		}

		$user = $auth->getUser();
		$commentId = $this->blogService->addComment( $title, $user, $content, $parentId );

		return $this->getResponseFactory()->createJson( [
			'status'    => 'ok',
			'commentId' => $commentId
		] );
	}

}
