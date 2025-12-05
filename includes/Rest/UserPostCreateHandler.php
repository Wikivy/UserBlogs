<?php

namespace Wikivy\UserBlogs\Rest;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Storage\EditResult;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Wikivy\UserBlogs\Services\BlogService;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\Revision\SlotRecord;

class UserPostCreateHandler extends SimpleHandler
{
	public function __construct(
		private readonly BlogService $blogService,
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	public function getParamSettings(): array
	{
		return [
			'username' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string'
			]
		];
	}

	public function run(): ResponseInterface {
		$params = $this->getValidatedParams();
		$pathUsername = $params['username'];

		$authority = $this->getAuthority();
		$user = $authority->getUser();

		if ( !$authority->isAllowed( 'userblogs-post' ) ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'No permission to create blog posts' ],
				403
			);
		}

		// By default, only allow creating posts on your own blog.
		if ( $user->getName() !== $pathUsername ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'forbidden', 'message' => 'Cannot create posts for another user' ],
				403
			);
		}

		$data = json_decode( $this->getRequest()->getBody()->getContents(), true ) ?: [];
		$titleText = trim( $data['title'] ?? '' );
		$content   = $data['content'] ?? '';

		if ( $titleText === '' ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'invalid', 'message' => 'Missing or empty title' ],
				400
			);
		}

		// Build User_blog:Username/Post title
		$fullPageName = $pathUsername . '/' . $titleText;
		$title = Title::makeTitleSafe( NS_USER_BLOG, $fullPageName );
		if ( !$title ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'invalid', 'message' => 'Invalid blog post title' ],
				400
			);
		}

		// Create page content as wikitext
		$contentObj = ContentHandler::makeContent( $content, $title, CONTENT_MODEL_WIKITEXT );

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, $contentObj );
		$pageUpdater->setCause('Create blog post' );
		$result = $pageUpdater->saveRevision( EDIT_NEW );

		if ( !$result instanceof EditResult || !$result->isOK() ) {
			return $this->getResponseFactory()->createJson(
				[ 'error' => 'editfailed', 'message' => 'Failed to create blog post' ],
				500
			);
		}

		// Register the post in metadata table
		$this->blogService->registerPost( $title, $user );

		return $this->getResponseFactory()->createJson( [
			'status'  => 'ok',
			'pageId'  => $title->getArticleID(),
			'title'   => $title->getPrefixedText(),
			'url'     => $title->getFullURL(),
		] );
	}
}
