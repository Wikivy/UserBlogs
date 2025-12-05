<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use Wikivy\UserBlogs\Services\BlogService;
use Wikimedia\ParamValidator\ParamValidator;

class CommentFavoriteHandler extends SimpleHandler
{
	public function __construct(
		private readonly BlogService $blogService
	) {
	}

	public function getParamSettings(): array
	{
		return [
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

		$auth = $this->getAuthority();
		$user = $auth->getUser();
		if ( !$user->isRegistered() ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'Login required' ],
				403
			);
		}

		$data = json_decode( $this->getRequest()->getBody()->getContents(), true ) ?: [];
		$fav = isset( $data['favorite'] ) ? (bool)$data['favorite'] : true;

		if ( !$this->blogService->setCommentFavorite( $commentId, $user, $fav ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'Not allowed to favorite this comment' ],
				403
			);
		}

		return $this->getResponseFactory()->createJson( [
			'status'   => 'ok',
			'favorite' => $fav
		] );
	}
}
