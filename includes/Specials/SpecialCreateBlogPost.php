<?php

namespace Wikivy\UserBlogs\Specials;

use HTMLForm;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\HTMLForm\HTMLFormField;
use Wikivy\UserBlogs\Services\BlogService;

class SpecialCreateBlogPost extends SpecialPage
{
	public function __construct(
		private readonly BlogService $blogService,
		private readonly WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( 'CreateBlogPost' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ): void
	{
		$this->setHeaders();

		$request = $this->getRequest();
		$user = $this->getUser();
		$out = $this->getOutput();

		if ( !$user->isRegistered() ) {
			throw new PermissionsError( 'userblogs-post' );
		}

		$authority = $this->getAuthority();
		if ( !$authority->isAllowed( 'userblogs-post' ) ) {
			throw new PermissionsError( 'userblogs-post' );
		}

		// Default to the current user's name as the blog owner
		$username = $user->getName();

		$out->setPageTitle($this->msg("userblogs-createblogpost-title", $username)->text());

		$formDescriptor = [
			'posttitle' => [
				'class' => 'HTMLTextField',
				'label-message' => 'userblogs-createblogpost-field-title',
				'maxlength' => 255,
				'size' => 60,
				'required' => true,
			],
			'postcontent' => [
				'class' => 'HTMLTextAreaField',
				'label-message' => 'userblogs-createblogpost-field-content',
				'rows' => 15,
				//'cols' => 80,
				'required' => true,
			]
		];

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setWrapperLegendMsg( 'userblogs-createblogpost-legend' );
		$form->setSubmitTextMsg( 'userblogs-createblogpost-submit' );
		$form->setSubmitCallback( function ( array $data ) use ( $username, $user, $out ) {
			return $this->onSubmit( $data, $username, $user, $out );
		} );

		$form->show();
	}

	/**
	 * Handles form submission.
	 *
	 * @param array $data
	 * @param string $username
	 * @param \User $user
	 * @param \OutputPage $out
	 * @return bool|\Status
	 */
	private function onSubmit( array $data, string $username, \User $user, \OutputPage $out ) {
		$titleText = trim( $data['posttitle'] );
		$contentText = (string) $data['postcontent'];
		//$summary = trim((string) $data['summary']);
		//$summary = '';

		if ( $titleText === '' ) {
			$out->addWikiMsg( 'userblogs-createblogpost-error-missingtitle' );
			return false;
		}

		// Build full page name: User_blog:Username/Post title
		$fullPageName = $username . '/' . $titleText;
		$title = Title::makeTitleSafe( NS_USER_BLOG, $fullPageName );
		if ( !$title ) {
			$out->addWikiMsg( 'userblogs-createblogpost-error-invalidtitle' );
			return false;
		}

		if ( $title->exists() ) {
			$out->addWikiMsg( 'userblogs-createblogpost-error-exists', $title->getPrefixedText() );
			return false;
		}

		// Create wikitext content
		$contentObj = ContentHandler::makeContent(
			$contentText,
			$title,
			CONTENT_MODEL_WIKITEXT
		);

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, $contentObj );

		/*if ( $summary === '' ) {
			$summary = $this->msg( 'userblogs-createblogpost-default-summary' )->text();
		}*/

		$summary = $this->msg( 'userblogs-createblogpost-default-summary' )->text();

		$pageUpdater->setCause( $summary );
		$result = $pageUpdater->saveRevision( $summary);

		if ( !$result) {
			$out->addWikiTextAsContent(
				$this->msg( 'userblogs-createblogpost-error-editfailed' )->text()
			);
			return false;
		}

		// Register metadata row
		$this->blogService->registerPost( $title, $user );

		// Redirect to the new blog post
		$out->redirect( $title->getFullURL() );
		return true;
	}
}
