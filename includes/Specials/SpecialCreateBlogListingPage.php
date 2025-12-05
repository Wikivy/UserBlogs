<?php

namespace Wikivy\UserBlogs\Specials;

use HTMLForm;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Revision\SlotRecord;

class SpecialCreateBlogListingPage extends SpecialPage
{
	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( 'CreateBlogListingPage' );
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		$this->setHeaders();

		$out = $this->getOutput();
		$user = $this->getUser();
		$authority = $this->getAuthority();

		// Reuse userblogs-post right as "can manage blog listings"
		if ( !$user->isRegistered() || !$authority->isAllowed( 'userblogs-post' ) ) {
			throw new \PermissionsError( 'userblogs-post' );
		}

		$out->setPageTitle( $this->msg( 'userblogs-createlisting-title' ) );

		$formDescriptor = [
			'pagetitle' => [
				'class' => 'HTMLTextField',
				'label-message' => 'userblogs-createlisting-field-pagetitle',
				'maxlength' => 255,
				'size' => 60,
				'required' => true,
				'placeholder' => 'Blog:My_category_posts'
			],
			'category' => [
				'class' => 'HTMLTextField',
				'label-message' => 'userblogs-createlisting-field-category',
				'maxlength' => 255,
				'size' => 60,
				'required' => true,
				'placeholder' => 'My category (without "Category:")'
			],
			'limit' => [
				'class' => 'HTMLTextField',
				'label-message' => 'userblogs-createlisting-field-limit',
				'size' => 10,
				'default' => '20',
			],
		];

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form->setWrapperLegendMsg( 'userblogs-createlisting-legend' );
		$form->setSubmitTextMsg( 'userblogs-createlisting-submit' );

		$form->setSubmitCallback( function ( array $data ) use ( $out, $user ) {
			return $this->onSubmit( $data, $user, $out );
		} );

		$form->show();
	}

	private function onSubmit( array $data, \User $user, OutputPage $out ): bool
	{
		$pageTitleText = trim( (string)$data['pagetitle'] );
		$categoryText  = trim( (string)$data['category'] );
		$limitText     = trim( (string)$data['limit'] );

		if ( $pageTitleText === '' ) {
			$out->addWikiMsg( 'userblogs-createlisting-error-missing-pagetitle' );
			return false;
		}
		if ( $categoryText === '' ) {
			$out->addWikiMsg( 'userblogs-createlisting-error-missing-category' );
			return false;
		}

		$limit = (int)$limitText;
		if ( $limit <= 0 ) {
			$limit = 20;
		}

		$title = Title::newFromText( $pageTitleText );
		if ( !$title || $title->isSpecialPage() ) {
			$out->addWikiMsg( 'userblogs-createlisting-error-invalid-pagetitle' );
			return false;
		}
		if ( $title->exists() ) {
			$out->addWikiMsg( 'userblogs-createlisting-error-exists', $title->getPrefixedText() );
			return false;
		}

		// Build wikitext content using the parser function
		$listingWikitext = '{{#userblogs_listing:' . $categoryText . '|limit=' . $limit . '}}';

		$contentObj = ContentHandler::makeContent(
			$listingWikitext,
			$title,
			CONTENT_MODEL_WIKITEXT
		);

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$pageUpdater->setContent( SlotRecord::MAIN, $contentObj );
		$pageUpdater->setCause(
			$this->msg( 'userblogs-createlisting-summary' )->inContentLanguage()->text()
		);

		$status = $pageUpdater->saveRevision( EDIT_NEW );
		if ( !$status) {
			$out->addWikiMsg( 'userblogs-createlisting-error-editfailed' );
			return false;
		}

		$out->redirect( $title->getFullURL() );
		return true;
	}
}
