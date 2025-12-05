<?php

namespace Wikivy\UserBlogs\Pages;

use MediaWiki\Html\Html;
use MediaWiki\Page\Article;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Skin\Skin;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\Title\Title;

class BlogPage extends Article
{
	/** @var Title|null */
	public $title = null;

	/**
	 * @var array Array containing blog authors' actor IDs, the format being [ 'actor' => <actor ID> ]
	 * @see BlogPage::getAuthors()
	 */
	public $authors = [];

	/**
	 * @var string Page content text
	 * @see BlogPage::setContent() and BlogPage::getContentText()
	 */
	public $pageContent;

	/**
	 * @param Title $title
	 */
	public function __construct(Title $title ) {
		parent::__construct( $title );
		$this->setContent();
	}

	public function setContent() {
		// Get the page content for later use
		$this->pageContent = self::getContentText($this);

		// If it's a redirect, in order to get the *real* content for later use
		// we have to load the text for the real page
		if ($this->getPage()->isRedirect()) {
			wfDebugLog('BlogPage', __METHOD__);

			$target = $this->getPage()->followRedirect();
			if (!$target instanceof Title) {
				// Correctly handle interwiki redirects and the like
				// WikiPage::followRedirect() can return either a Title, boolean
				// or a string! A string is returned for interwiki redirects,
				// and the string in question is the target URL with the rdfrom
				// URL parameter appended to it, which -- since it's an interwiki
				// URL -- won't resolve to a valid local Title.
				// Doing the redirection here is somewhat hacky, but ::getAuthors(),
				// which is called right after this function in the constructor,
				// attempts to read $this->pageContent...
				// @see https://github.com/Brickimedia/brickimedia/issues/370
				$this->getContext()->getOutput()->redirect($target);
			} else {
				$rarticle = new Article( $target );
				$this->pageContent = self::getContentText($rarticle);

				// If we don't clear, the page content will be [[redirect-blah]],
				// and not the actual page
				$this->clear();
			}
		}

	}

	private static function getContentText(Article $article) {
		$rev = $article->fetchRevisionRecord();

		if ($rev) {
			$content = $rev->getContent(
				SlotRecord::MAIN,
				RevisionRecord::FOR_THIS_USER,
				$article->getContext()->getUser()
			);

			if ($content && $content instanceof TextContent) {
				return $content->getText();
			}
		}

		return '';
	}

	/** @inheritDoc */
	public function view()
	{
		global $wgBlogPageDisplay;

		$context = $this->getContext();
		$user = $context->getUser();
		$output = $context->getOutput();

		$skin = $output->getSkin();

		wfDebugLog('BlogPage', __METHOD__);

		// Don't throw a bunch of E_NOTICEs when we're viewing the page of a
		// nonexistent blog post
		if ( !$this->getPage()->getId() ) {
			parent::view();
			return '';
		}

		// Don't display the sidebar etc. when a redirect page in the Blog: NS is
		// accessed with ?redirect=no in the URL
		if ( $this->getPage()->isRedirect() ) {
			parent::view();
			return '';
		}


		$output->setHTMLTitle($this->getTitle()->getText());
		$output->setPageTitle($this->getTitle()->getText());

		$output->addHTML( "\n<!--end Article::view-->\n" );
	}


}
