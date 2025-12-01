<?php

namespace Wikivy\UserBlogs\Specials;

use Html;
use SpecialPage;
use Title;
use User;
use WikiPage;
use WikitextContent;

class SpecialCreateBlogPost extends SpecialPage {
	
	public function __construct() {
		parent::__construct( 'CreateBlogPost', 'edit' );
	}

	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( !$user->isLoggedIn() ) {
			$out->addHTML( $this->msg( 'userblogs-must-login' )->escaped() );
			return;
		}

		$out->addModules( 'ext.userblogs' );
		$out->setPageTitle( $this->msg( 'userblogs-create-title' ) );

		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'create' ) {
			$this->handleCreatePost( $request, $user );
			return;
		}

		$this->showCreateForm();
	}

	private function showCreateForm() {
		$out = $this->getOutput();
		
		$html = Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL(),
			'class' => 'userblogs-create-form'
		] );
		
		$html .= Html::element( 'h2', [], $this->msg( 'userblogs-create-post' )->text() );
		
		$html .= Html::element( 'label', [ 'for' => 'blog-title' ], 
			$this->msg( 'userblogs-title-label' )->text() );
		$html .= Html::input( 'title', '', 'text', [
			'id' => 'blog-title',
			'required' => true,
			'class' => 'userblogs-input'
		] );
		
		$html .= Html::element( 'label', [ 'for' => 'blog-content' ], 
			$this->msg( 'userblogs-content-label' )->text() );
		$html .= Html::textarea( 'content', '', [
			'id' => 'blog-content',
			'rows' => 15,
			'required' => true,
			'class' => 'userblogs-textarea'
		] );
		
		$html .= Html::hidden( 'action', 'create' );
		$html .= Html::submitButton( $this->msg( 'userblogs-submit' )->text(), [
			'class' => 'userblogs-submit'
		] );
		
		$html .= Html::closeElement( 'form' );
		
		$out->addHTML( $html );
	}

	private function handleCreatePost( $request, $user ) {
		$title = $request->getVal( 'title' );
		$content = $request->getVal( 'content' );
		
		if ( empty( $title ) || empty( $content ) ) {
			$this->getOutput()->addHTML( 
				Html::element( 'p', [ 'class' => 'error' ], 
					$this->msg( 'userblogs-error-empty' )->text() 
				)
			);
			return;
		}

		// Create sanitized page title
		$pageTitle = $this->sanitizeTitle( $title );
		$fullTitle = Title::makeTitleSafe( 
			NS_USER_BLOG, 
			$user->getName() . '/' . $pageTitle 
		);

		if ( !$fullTitle ) {
			$this->getOutput()->addHTML( 
				Html::element( 'p', [ 'class' => 'error' ], 
					$this->msg( 'userblogs-error-invalid-title' )->text() 
				)
			);
			return;
		}

		// Check if page already exists
		if ( $fullTitle->exists() ) {
			$this->getOutput()->addHTML( 
				Html::element( 'p', [ 'class' => 'error' ], 
					$this->msg( 'userblogs-error-exists' )->text() 
				)
			);
			return;
		}

		// Create the blog post page
		$page = WikiPage::factory( $fullTitle );
		$pageContent = new WikitextContent( $content );
		
		$status = $page->doUserEditContent(
			$pageContent,
			$user,
			$this->msg( 'userblogs-edit-summary' )->text(),
			EDIT_NEW
		);

		if ( $status->isOK() ) {
			// Update recent posts page if blog is public
			$this->updateRecentPosts( $user, $fullTitle );
			
			// Redirect to the new blog post
			$this->getOutput()->redirect( $fullTitle->getFullURL() );
		} else {
			$this->getOutput()->addHTML( 
				Html::element( 'p', [ 'class' => 'error' ], 
					$status->getMessage()->text()
				)
			);
		}
	}

	private function sanitizeTitle( $title ) {
		// Replace spaces with underscores and remove special characters
		$title = trim( $title );
		$title = str_replace( ' ', '_', $title );
		$title = preg_replace( '/[^\w\-]/', '', $title );
		return $title;
	}

	private function updateRecentPosts( $user, $postTitle ) {
		// Check if user's blog is public
		$dbr = wfGetDB( DB_REPLICA );
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

		if ( !$isPublic ) {
			return; // Don't update recent posts if blog is private
		}

		// Get or create Blog:Recent_posts
		$recentPostsTitle = Title::makeTitleSafe( NS_BLOG, 'Recent_posts' );
		$recentPage = WikiPage::factory( $recentPostsTitle );
		
		// Get current content
		$currentContent = '';
		if ( $recentPostsTitle->exists() ) {
			$currentContent = $recentPage->getContent()->getText();
		} else {
			$currentContent = "== Recent Blog Posts ==\n\n";
		}

		// Add new post at the top
		$timestamp = date( 'Y-m-d H:i:s' );
		$newEntry = sprintf(
			"* '''[[%s|%s]]''' by [[User:%s|%s]] - %s\n",
			$postTitle->getPrefixedText(),
			$postTitle->getSubpageText(),
			$user->getName(),
			$user->getName(),
			$timestamp
		);

		// Insert after the header
		$lines = explode( "\n", $currentContent );
		$headerFound = false;
		$newContent = '';
		
		foreach ( $lines as $line ) {
			$newContent .= $line . "\n";
			if ( !$headerFound && strpos( $line, '==' ) === 0 ) {
				$newContent .= "\n" . $newEntry;
				$headerFound = true;
			}
		}

		if ( !$headerFound ) {
			$newContent = $currentContent . "\n" . $newEntry;
		}

		// Limit to 50 most recent posts
		$entries = preg_split( '/^\*/m', $newContent );
		$header = array_shift( $entries );
		$entries = array_slice( $entries, 0, 50 );
		$newContent = $header . '*' . implode( '*', $entries );

		// Update the page
		$pageContent = new WikitextContent( $newContent );
		$recentPage->doUserEditContent(
			$pageContent,
			User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] ),
			$this->msg( 'userblogs-recent-update' )->text(),
			EDIT_UPDATE | EDIT_SUPPRESS_RC
		);
	}

	protected function getGroupName() {
		return 'users';
	}
}
