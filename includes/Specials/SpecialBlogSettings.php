<?php

namespace Wikivy\UserBlogs\Specials;

use Html;
use Linker;
use SpecialPage;
use Title;

class SpecialBlogSettings extends SpecialPage {
	
	public function __construct() {
		parent::__construct( 'BlogSettings' );
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isLoggedIn() ) {
			$out->addHTML( $this->msg( 'userblogs-must-login' )->escaped() );
			return;
		}

		$out->addModules( 'ext.userblogs' );
		$out->setPageTitle( $this->msg( 'userblogs-settings-title' ) );

		if ( $request->wasPosted() ) {
			$this->handleSaveSettings( $request, $user );
			return;
		}

		$this->showSettingsForm( $user );
	}

	private function showSettingsForm( $user ) {
		$out = $this->getOutput();
		$dbr = wfGetDB( DB_REPLICA );

		// Get current setting
		$isPublic = $dbr->selectField(
			'user_blog_settings',
			'ubs_is_public',
			[ 'ubs_user_id' => $user->getId() ],
			__METHOD__
		);

		// Default to public if no setting exists
		if ( $isPublic === false ) {
			$isPublic = 1;
		}

		$html = Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL(),
			'class' => 'userblogs-settings-form'
		] );

		$html .= Html::element( 'h2', [], $this->msg( 'userblogs-privacy-settings' )->text() );
		
		$html .= Html::openElement( 'div', [ 'class' => 'userblogs-setting' ] );
		$html .= Html::check( 'is_public', (bool)$isPublic, [ 'id' => 'blog-public' ] );
		$html .= ' ';
		$html .= Html::label( 
			$this->msg( 'userblogs-public-label' )->text(), 
			'blog-public' 
		);
		$html .= Html::closeElement( 'div' );

		$html .= Html::element( 'p', [ 'class' => 'userblogs-help' ],
			$this->msg( 'userblogs-public-help' )->text()
		);

		$html .= Html::submitButton( $this->msg( 'userblogs-save-settings' )->text(), [
			'class' => 'userblogs-submit'
		] );

		$html .= Html::closeElement( 'form' );

		// Show user's blog page link
		$blogTitle = Title::makeTitleSafe( NS_USER_BLOG, $user->getName() );
		$html .= Html::element( 'h2', [ 'style' => 'margin-top: 30px;' ], 
			$this->msg( 'userblogs-your-blog' )->text() 
		);
		$html .= Html::rawElement( 'p', [],
			$this->msg( 'userblogs-blog-page-info' )->text() . ' ' .
			Linker::link( $blogTitle, $blogTitle->getPrefixedText() )
		);

		$out->addHTML( $html );
	}

	private function handleSaveSettings( $request, $user ) {
		$isPublic = $request->getBool( 'is_public' ) ? 1 : 0;

		$dbw = wfGetDB( DB_PRIMARY );
		
		// Check if setting exists
		$exists = $dbw->selectField(
			'user_blog_settings',
			'ubs_user_id',
			[ 'ubs_user_id' => $user->getId() ],
			__METHOD__
		);

		if ( $exists ) {
			$dbw->update(
				'user_blog_settings',
				[ 'ubs_is_public' => $isPublic ],
				[ 'ubs_user_id' => $user->getId() ],
				__METHOD__
			);
		} else {
			$dbw->insert(
				'user_blog_settings',
				[
					'ubs_user_id' => $user->getId(),
					'ubs_is_public' => $isPublic
				],
				__METHOD__
			);
		}

		$this->getOutput()->addHTML(
			Html::element( 'p', [ 'class' => 'success' ],
				$this->msg( 'userblogs-settings-saved' )->text()
			)
		);

		$this->showSettingsForm( $user );
	}

	protected function getGroupName() {
		return 'users';
	}
}
