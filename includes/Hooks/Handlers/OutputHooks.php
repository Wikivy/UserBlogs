<?php

namespace Wikivy\UserBlogs\Hooks\Handlers;

use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Html\Html;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikivy\UserBlogs\Pages\BlogPage;
use Wikivy\UserBlogs\Services\BlogService;

class OutputHooks implements BeforePageDisplayHook
{

	public function __construct(
		private readonly BlogService $blogService,
		private readonly UserFactory $userFactory
	){
	}

	public function onBeforePageDisplay($out, $skin): void
	{
		wfDebugLog("UserBlogsOutputHooks", "BeforePageDisplay hook triggered\n\n");

		/** @var SkinTemplate $skin */
		$title = $skin->getTitle();
		if (!$title instanceof Title) {
			wfDebugLog("UserBlogsOutputHooks", $title);
			return;
		}

		$out->addModuleStyles('ext.UserBlogs.styles');

		// Blog:Recent_posts index
		if ($title->inNamespace(NS_BLOG) && $title->isSpecialPage() === false
		&& $title->getText() === 'Recent posts') {
			$this->renderRecentPostsPage( $skin, $title );
			return;
		}

		// Blog:<CategoryName> – blog category listing
		if ( $title->inNamespace( NS_BLOG ) && !$title->isSubpage() ) {
			$this->renderBlogCategoryPage( $skin, $title );
			return;
		}

		// User_blog:Username (no subpage) -> user blog index
		if ( $title->inNamespace( NS_USER_BLOG ) && !$title->isSubpage() ) {
			$this->renderUserBlogIndex( $skin, $title );
			return;
		}

		// User_blog:Username/Post_title -> show comments block under content
		if ( $title->inNamespace( NS_USER_BLOG ) && $title->isSubpage() ) {
			$this->appendCommentsBlock( $skin, $title );
		}
	}

	private function renderRecentPostsPage( SkinTemplate $skin, Title $title ): void {
		$out = $skin->getOutput();
		$out->setPageTitle( wfMessage( 'userblogs-recent-posts-title' )->text() );

		$posts = $this->blogService->getRecentPosts( 50, 0 );

		if ( !$posts ) {
			$out->addWikiMsg( 'userblogs-recent-posts-empty' );
			return;
		}

		$listItems = '';
		foreach ( $posts as $post ) {
			/** @var Title $postTitle */
			$postTitle = $post['title'];

			$link = Html::element(
				'a',
				[ 'href' => $postTitle->getLocalURL() ],
				$postTitle->getText()
			);

			$userName = $post['username'];
			$userLink = Html::element(
				'a',
				[ 'href' => Title::makeTitle( NS_USER, $userName )->getLocalURL() ],
				$userName
			);

			$meta = Html::rawElement(
				'span',
				[ 'class' => 'userblogs-post-meta' ],
				wfMessage(
					'userblogs-recent-posts-meta',
					$userLink,
					wfTimestamp( TS_ISO_8601, $post['created'] )
				)->parse()
			);

			$listItems .= Html::rawElement(
				'li',
				[ 'class' => 'userblogs-post-item' ],
				Html::rawElement( 'div', [ 'class' => 'userblogs-post-title' ], $link ) .
				$meta
			);
		}

		$out->clearHTML(); // ignore any wikitext content if the page exists
		$out->addHTML(
			Html::rawElement(
				'ul',
				[ 'class' => 'userblogs-post-list' ],
				$listItems
			)
		);
	}

	private function renderBlogCategoryPage( SkinTemplate $skin, Title $title ): void {
		$out = $skin->getOutput();

		$categoryName = $title->getText();

		$out->setPageTitle(
			wfMessage( 'userblogs-blogcategory-title', $categoryName )->text()
		);

		// Fetch posts in Category:<CategoryName>
		$posts = $this->blogService->getPostsByCategory( $categoryName, 50, 0 );

		// Optional info blurb
		$out->clearHTML();
		$out->addWikiTextAsContent(
			wfMessage( 'userblogs-blogcategory-intro', $categoryName )->plain()
		);

		if ( !$posts ) {
			$out->addWikiTextAsContent(
				wfMessage( 'userblogs-blogcategory-empty', $categoryName )->plain()
			);
			return;
		}

		$listItems = '';
		foreach ( $posts as $post ) {
			/** @var Title $postTitle */
			$postTitle = $post['title'];

			$link = Html::element(
				'a',
				[ 'href' => $postTitle->getLocalURL() ],
				$postTitle->getSubpageText()
			);

			$meta = Html::rawElement(
				'span',
				[ 'class' => 'userblogs-post-meta' ],
				wfMessage(
					'userblogs-blogcategory-meta',
					$post['username'],
					wfTimestamp( TS_ISO_8601, $post['created'] )
				)->parse()
			);

			$listItems .= Html::rawElement(
				'li',
				[ 'class' => 'userblogs-post-item' ],
				Html::rawElement( 'div', [ 'class' => 'userblogs-post-title' ], $link ) .
				$meta
			);
		}

		$out->addHTML(
			Html::rawElement(
				'ul',
				[ 'class' => 'userblogs-post-list userblogs-blogcategory-list' ],
				$listItems
			)
		);
	}

	private function renderUserBlogIndex( SkinTemplate $skin, Title $title ): void {
		$out = $skin->getOutput();

		$username = $title->getText(); // User_blog:Username
		$out->setPageTitle(
			wfMessage( 'userblogs-user-index-title', $username )->text()
		);

		$posts = $this->blogService->getUserPosts( $username, 50, 0 );

		// "Create new blog post" link
		$createUrl = \SpecialPage::getTitleFor( 'CreateBlogPost' )->getLocalURL();
		$createLink = Html::element(
			'a',
			[ 'href' => $createUrl, 'class' => 'userblogs-create-link' ],
			wfMessage( 'userblogs-createblogpost-link-label' )->text()
		);

		$headerHtml = Html::rawElement(
			'div',
			[ 'class' => 'userblogs-user-index-header' ],
			Html::element(
				'span',
				[ 'class' => 'userblogs-user-index-heading' ],
				wfMessage( 'userblogs-user-index-heading', $username )->text()
			) . ' ' . $createLink
		);

		if ( !$posts ) {
			$out->clearHTML();
			$out->addHTML( $headerHtml );
			$out->addWikiMsg( 'userblogs-user-index-empty', $username );
			return;
		}

		$listItems = '';
		foreach ( $posts as $post ) {
			/** @var Title $postTitle */
			$postTitle = $post['title'];

			$link = Html::element(
				'a',
				[ 'href' => $postTitle->getLocalURL() ],
				$postTitle->getSubpageText() // show only the post title part
			);

			$meta = Html::rawElement(
				'span',
				[ 'class' => 'userblogs-post-meta' ],
				wfMessage(
					'userblogs-user-index-meta',
					wfTimestamp( TS_ISO_8601, $post['created'] )
				)->parse()
			);

			$listItems .= Html::rawElement(
				'li',
				[ 'class' => 'userblogs-post-item' ],
				Html::rawElement( 'div', [ 'class' => 'userblogs-post-title' ], $link ) .
				$meta
			);
		}

		$out->clearHTML();
		$out->addHTML(
			Html::rawElement(
				'ul',
				[ 'class' => 'userblogs-post-list userblogs-user-index-list' ],
				$listItems
			)
		);
	}

	private function appendCommentsBlock( SkinTemplate $skin, Title $title ): void {
		$out = $skin->getOutput();
		$request = $skin->getRequest();
		$user = $skin->getUser();
		$authority = $skin->getAuthority();

		$postPageId = $title->getArticleID();
		if ( !$postPageId ) {
			return;
		}

		$out->addModuleStyles( 'ext.UserBlogs.styles' );

		// --- 1) Handle form submission (simple top-level comment) ---
		if (
			$request->wasPosted()
			&& $request->getVal( 'userblogs-comment-action' ) === 'add'
		) {
			if ( !$user->isRegistered() || !$authority->isAllowed( 'userblogs-comment' ) ) {
				$out->addWikiMsg( 'userblogs-comment-error-permission' );
			} else {
				$token = $request->getVal( 'userblogs-comment-token' );
				if ( !$skin->getCsrfTokenSet()->matchToken( $token, 'userblogs-comment') ) {
					$out->addWikiMsg( 'userblogs-comment-error-badtoken' );
				} else {
					$content = trim( $request->getText( 'userblogs-comment-content' ) );
					$parentId = $request->getInt( 'userblogs-comment-parent', 0 );
					$parentId = $parentId > 0 ? $parentId : null;

					if ( $content === '' ) {
						$out->addWikiMsg( 'userblogs-comment-error-empty' );
					} else {
						// BlogService method to insert comment into userblogs_comments
						$status = $this->blogService->addComment(
							$title,
							$user,
							$content,
							$parentId
						);

						if ( !$status->isOK() ) {
							$out->addWikiMsg( 'userblogs-comment-error-generic' );
						} else {
							// Redirect to avoid resubmission on reload
							$out->redirect(
								$title->getLocalURL( [ ] ) . '#userblogs-comments'
							);
							return;
						}
					}
				}
			}
		}

		// --- 2) Render comment form + comments block ---
		$formHtml = $this->buildCommentFormHtml( $skin, $title );
		$commentsHtml = $this->buildCommentsHtml( $skin, $title );

		$out->addHTML(
			Html::rawElement(
				'div',
				[ 'id' => 'userblogs-comments', 'class' => 'userblogs-comments-wrapper' ],
				$formHtml . $commentsHtml
			)
		);
	}

	private function buildCommentsHtml( SkinTemplate $skin, Title $title ): string
	{
		$comments = $this->blogService->getCommentsForPost( $title, 100, 0 );
		if ( !$comments ) {
			return Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comments-empty' ],
				wfMessage( 'userblogs-comments-empty' )->escaped()
			);
		}

		// Build a simple threaded list (flat nesting for now).
		$listItems = '';
		foreach ( $comments as $comment ) {
			$user = $this->userFactory->newFromId($comment['author']);
			$authorName = $user ? $user->getName() : wfMessage( 'userblogs-unknown-author' )->text();

			$authorLink = Html::element(
				'a',
				[
					'href' => Title::makeTitle( NS_USER, $authorName )->getLocalURL()
				],
				$authorName
			);


			$header = Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-header' ],
				$authorLink . ' • ' .
				wfTimestamp( TS_ISO_8601, $comment['created'] )
			);

			$body = Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-body' ],
				// raw wikitext here – for v1 we just show it as plain text;
				// parsing can be done later via a parser or REST client.
				htmlspecialchars( $comment['content'] )
			);

			$listItems .= Html::rawElement(
				'li',
				[ 'class' => 'userblogs-comment-item' ],
				$header . $body
			);
		}

		return Html::rawElement(
			'div',
			[ 'class' => 'userblogs-comments-block' ],
			Html::element(
				'h2',
				[ 'class' => 'userblogs-comments-heading' ],
				wfMessage( 'userblogs-comments-heading' )->text()
			) .
			Html::rawElement(
				'ul',
				[ 'class' => 'userblogs-comment-list' ],
				$listItems
			)
		);
	}

	private function buildCommentFormHtml( SkinTemplate $skin, Title $title ): string {
		$user = $skin->getUser();
		$authority = $skin->getAuthority();

		// If not logged in, show a hint instead of the form
		if ( !$user->isRegistered() ) {
			return Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-loginrequired' ],
				wfMessage( 'userblogs-comment-loginrequired' )->parse()
			);
		}

		if ( !$authority->isAllowed( 'userblogs-comment' ) ) {
			return Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-permission' ],
				wfMessage( 'userblogs-comment-error-permission' )->parse()
			);
		}

		$token = $skin->getCsrfTokenSet()->getToken('userblogs-comment');

		$textarea = Html::element(
			'textarea',
			[
				'name' => 'userblogs-comment-content',
				'rows' => 5,
				'cols' => 80,
				'class' => 'userblogs-comment-textarea'
			],
			''
		);

		$hiddenToken = Html::hidden( 'userblogs-comment-token', $token );
		// For now, only top-level comments; JS can later change this for replies
		$hiddenParent = Html::hidden( 'userblogs-comment-parent', '0' );
		$hiddenAction = Html::hidden( 'userblogs-comment-action', 'add' );

		$submit = Html::element(
			'button',
			[
				'type'  => 'submit',
				'class' => 'userblogs-comment-submit'
			],
			wfMessage( 'userblogs-comment-submit' )->text()
		);

		$form = Html::rawElement(
			'form',
			[
				'method' => 'post',
				'action' => $title->getLocalURL( '#userblogs-comments' ),
				'class'  => 'userblogs-comment-form'
			],
			$hiddenToken .
			$hiddenParent .
			$hiddenAction .
			Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-field' ],
				$textarea
			) .
			Html::rawElement(
				'div',
				[ 'class' => 'userblogs-comment-actions' ],
				$submit
			)
		);

		return Html::rawElement(
			'div',
			[ 'class' => 'userblogs-comment-form-wrapper' ],
			Html::rawElement(
				'h2',
				[ 'class' => 'userblogs-comment-form-heading' ],
				wfMessage( 'userblogs-comment-form-heading' )->text()
			) .
			$form
		);
	}
}
