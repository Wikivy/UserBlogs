<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use Wikivy\UserBlogs\Services\BlogService;
use Wikimedia\ParamValidator\ParamValidator;

class PostCommentEditHandler extends SimpleHandler
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
			'commentId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'integer'
			]
		];
	}

	public function run(): ResponseInterface {
		$params = $this->getValidatedParams();
		$commentId = (int)$params['commentId'];

		$request = $this->getRequest();
		$data = json_decode( $request->getBody()->getContents(), true ) ?: [];
		$content = $data['content'] ?? null;

		if ( $content === null ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'invalid', 'message' => 'Missing content' ],
				400
			);
		}

		$auth = $this->getAuthority();
		$user = $auth->getUser();

		// You'd implement a canEditComment + editComment in BlogService
		if ( !$this->blogService->editComment( $commentId, $user, $content ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'Cannot edit comment' ],
				403
			);
		}

		return $this->getResponseFactory()->createJson( [ 'status' => 'ok' ] );
	}
}
