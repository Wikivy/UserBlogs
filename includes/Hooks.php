<?php

namespace Wikivy\UserBlogs;

use DatabaseUpdater;
use Html;
use Linker;
use OutputPage;
use Skin;
use SkinTemplate;
use Title;
use User;
use WikitextContent;

class Hooks {
	
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = dirname( __DIR__ );
		$updater->addExtensionTable(
			'user_blog_settings',
			$dir . '/sql/user_blog_settings.sql'
		);
		$updater->addExtensionTable(
			'user_blog_comments',
			$dir . '/sql/user_blog_comments.sql'
		);
		return true;
	}

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$title = $out->getTitle();
		
		// If we're on a User_blog namespace page, add blog styling
		if ( $title && $title->getNamespace() === NS_USER_BLOG ) {
			$out->addModules( 'ext.userblogs' );
			
			// If this is a user's main blog page (User_blog:Username without subpage)
			if ( strpos( $title->getText(), '/' ) === false ) {
				self::showUserBlogIndex( $out, $title );
			} else {
				// This is an individual blog post, show comments
				$out->addModules( 'ext.userblogs.comments' );
				self::showComments( $out, $title );
			}
		}
		
		return true;
	}

	private static function showUserBlogIndex( OutputPage $out, Title $title ) {
		$username = $title->getText();
		$user = User::newFromName( $username );
		
		if ( !$user || !$user->getId() ) {
			return;
		}

		// Check if blog is public
		$dbr = wfGetDB( DB_REPLICA );
		$isPublic = $dbr->selectField(
			'user_blog_settings',
			'ubs_is_public',
			[ 'ubs_user_id' => $user->getId() ],
			__METHOD__
		);

		if ( $isPublic === false ) {
			$isPublic = 1; // Default to public
		}

		$currentUser = $out->getUser();
		$isOwner = $currentUser->getId() === $user->getId();

		if ( !$isPublic && !$isOwner ) {
			$out->prependHTML( 
				Html::element( 'div', [ 'class' => 'userblogs-private' ],
					wfMessage( 'userblogs-blog-private' )->text()
				)
			);
			return;
		}

		// Get all blog posts for this user
		$posts = self::getUserBlogPosts( $username );
		
		if ( empty( $posts ) ) {
			$out->prependHTML(
				Html::element( 'div', [ 'class' => 'userblogs-empty' ],
					wfMessage( 'userblogs-no-posts' )->text()
				)
			);
			return;
		}

		// Display list of posts
		$html = Html::element( 'h2', [], wfMessage( 'userblogs-blog-posts' )->text() );
		$html .= Html::openElement( 'ul', [ 'class' => 'userblogs-post-list' ] );
		
		foreach ( $posts as $post ) {
			$postTitle = Title::newFromText( $post );
			if ( $postTitle ) {
				$html .= Html::openElement( 'li' );
				$html .= Linker::link( $postTitle, $postTitle->getSubpageText() );
				$html .= Html::closeElement( 'li' );
			}
		}
		
		$html .= Html::closeElement( 'ul' );
		$out->prependHTML( $html );
	}

	private static function getUserBlogPosts( $username ) {
		$dbr = wfGetDB( DB_REPLICA );
		
		$res = $dbr->select(
			'page',
			[ 'page_title' ],
			[
				'page_namespace' => NS_USER_BLOG,
				'page_title ' . $dbr->buildLike( $username . '/', $dbr->anyString() )
			],
			__METHOD__,
			[ 'ORDER BY' => 'page_id DESC' ]
		);

		$posts = [];
		foreach ( $res as $row ) {
			$posts[] = 'User_blog:' . str_replace( '_', ' ', $row->page_title );
		}
		
		return $posts;
	}

	private static function showComments( OutputPage $out, Title $title ) {
		$pageId = $title->getArticleID();
		
		if ( !$pageId ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		
		// Get all comments for this post
		$res = $dbr->select(
			'user_blog_comments',
			[ 'ubc_id', 'ubc_user_id', 'ubc_parent_id', 'ubc_content', 'ubc_timestamp' ],
			[ 'ubc_page_id' => $pageId ],
			__METHOD__,
			[ 'ORDER BY' => 'ubc_timestamp ASC' ]
		);

		$comments = [];
		foreach ( $res as $row ) {
			$comments[] = [
				'id' => $row->ubc_id,
				'user_id' => $row->ubc_user_id,
				'parent_id' => $row->ubc_parent_id,
				'content' => $row->ubc_content,
				'timestamp' => $row->ubc_timestamp
			];
		}

		// Build comment HTML
		$html = Html::openElement( 'div', [ 
			'class' => 'userblogs-comments-section',
			'id' => 'userblogs-comments',
			'data-page-id' => $pageId
		] );
		
		$html .= Html::element( 'h2', [], wfMessage( 'userblogs-comments-title' )->text() );
		
		// Comment form
		$user = $out->getUser();
		if ( $user->isLoggedIn() ) {
			$html .= self::buildCommentForm( 0 );
		} else {
			$html .= Html::element( 'p', [ 'class' => 'userblogs-login-prompt' ],
				wfMessage( 'userblogs-must-login-comment' )->text()
			);
		}

		// Display comments
		$html .= Html::openElement( 'div', [ 'class' => 'userblogs-comments-list' ] );
		$html .= self::buildCommentTree( $comments, 0, $out->getUser() );
		$html .= Html::closeElement( 'div' );
		
		$html .= Html::closeElement( 'div' );
		
		$out->addHTML( $html );
	}

	private static function buildCommentForm( $parentId = 0, $commentId = null ) {
		$formId = $commentId ? "comment-form-{$commentId}" : "comment-form-{$parentId}";
		
		$html = Html::openElement( 'div', [ 
			'class' => 'userblogs-comment-form',
			'id' => $formId,
			'style' => $parentId > 0 ? 'display:none;' : ''
		] );
		
		$html .= Html::textarea( 'comment-text', '', [
			'class' => 'userblogs-comment-textarea',
			'placeholder' => wfMessage( 'userblogs-comment-placeholder' )->text(),
			'rows' => 3
		] );
		
		$html .= Html::rawElement( 'div', [ 'class' => 'userblogs-comment-actions' ],
			Html::element( 'button', [
				'class' => 'userblogs-comment-submit',
				'data-parent-id' => $parentId
			], wfMessage( 'userblogs-comment-submit' )->text() ) .
			( $parentId > 0 ? Html::element( 'button', [
				'class' => 'userblogs-comment-cancel',
				'data-form-id' => $formId
			], wfMessage( 'userblogs-comment-cancel' )->text() ) : '' )
		);
		
		$html .= Html::closeElement( 'div' );
		
		return $html;
	}

	private static function buildCommentTree( $comments, $parentId, $currentUser ) {
		$html = '';
		
		foreach ( $comments as $comment ) {
			if ( $comment['parent_id'] != $parentId ) {
				continue;
			}

			$user = User::newFromId( $comment['user_id'] );
			$isOwner = $currentUser->getId() === $comment['user_id'];
			
			$html .= Html::openElement( 'div', [ 
				'class' => 'userblogs-comment',
				'data-comment-id' => $comment['id']
			] );
			
			$html .= Html::openElement( 'div', [ 'class' => 'userblogs-comment-header' ] );
			$html .= Html::rawElement( 'span', [ 'class' => 'userblogs-comment-author' ],
				Linker::link( $user->getUserPage(), $user->getName() )
			);
			$html .= Html::element( 'span', [ 'class' => 'userblogs-comment-time' ],
				wfTimestamp( TS_RFC2822, $comment['timestamp'] )
			);
			$html .= Html::closeElement( 'div' );
			
			$html .= Html::element( 'div', [ 'class' => 'userblogs-comment-content' ],
				$comment['content']
			);
			
			$html .= Html::openElement( 'div', [ 'class' => 'userblogs-comment-footer' ] );
			
			if ( $currentUser->isLoggedIn() ) {
				$html .= Html::element( 'button', [
					'class' => 'userblogs-comment-reply-btn',
					'data-comment-id' => $comment['id']
				], wfMessage( 'userblogs-comment-reply' )->text() );
			}
			
			if ( $isOwner || $currentUser->isAllowed( 'delete' ) ) {
				$html .= Html::element( 'button', [
					'class' => 'userblogs-comment-delete-btn',
					'data-comment-id' => $comment['id']
				], wfMessage( 'userblogs-comment-delete' )->text() );
			}
			
			$html .= Html::closeElement( 'div' );
			
			// Reply form (hidden by default)
			$html .= self::buildCommentForm( $comment['id'], $comment['id'] );
			
			// Nested replies
			$html .= Html::openElement( 'div', [ 'class' => 'userblogs-comment-replies' ] );
			$html .= self::buildCommentTree( $comments, $comment['id'], $currentUser );
			$html .= Html::closeElement( 'div' );
			
			$html .= Html::closeElement( 'div' );
		}
		
		return $html;
	}

	public static function onSkinTemplateNavigation( SkinTemplate &$skin, array &$links ) {
		$user = $skin->getUser();
		
		if ( $user->isLoggedIn() ) {
			$links['user-menu']['myblog'] = [
				'text' => wfMessage( 'userblogs-my-blog' )->text(),
				'href' => Title::makeTitleSafe( NS_USER_BLOG, $user->getName() )->getLocalURL(),
				'active' => false,
			];
		}
		
		return true;
	}
}
