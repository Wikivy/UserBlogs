<?php

namespace Wikivy\UserBlogs\Hooks\Handlers;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use Wikivy\UserBlogs\Services\BlogService;
use Parser;


class ParserHooks implements ParserFirstCallInitHook
{
	public function __construct(
		private readonly BlogService $blogService
	) {
	}

	public function onParserFirstCallInit( $parser ): void {
		/** @var Parser $parser */
		$parser->setFunctionHook(
			'userblogs_listing',
			[ $this, 'renderUserBlogsListing' ],
			Parser::SFH_OBJECT_ARGS
		);
	}

	/**
	 * {{#userblogs_listing:Category name|limit=20}}
	 *
	 * @param Parser $parser
	 * @param array $frame
	 * @param array $args
	 * @return string
	 */
	public function renderUserBlogsListing( Parser $parser, $frame, $args ) {
		// Parameter 1: category name
		$categoryRaw = $args[0] ?? null;
		if ( $categoryRaw === null ) {
			return Html::element(
				'div',
				[ 'class' => 'userblogs-listing-error' ],
				wfMessage( 'userblogs-listing-error-missing-category' )->text()
			);
		}

		$categoryName = trim( $frame->expand( $categoryRaw ) );
		if ( $categoryName === '' ) {
			return Html::element(
				'div',
				[ 'class' => 'userblogs-listing-error' ],
				wfMessage( 'userblogs-listing-error-missing-category' )->text()
			);
		}

		// Optional: limit=N in second param
		$limit = 20;
		if ( isset( $args[1] ) ) {
			$limitParam = trim( $frame->expand( $args[1] ) );
			if ( preg_match( '/^limit\s*=\s*(\d+)$/i', $limitParam, $m ) ) {
				$limit = max( 1, (int)$m[1] );
			}
		}

		$posts = $this->blogService->getPostsByCategory( $categoryName, $limit, 0 );
		if ( !$posts ) {
			return Html::element(
				'div',
				[ 'class' => 'userblogs-listing-empty' ],
				wfMessage( 'userblogs-listing-empty', $categoryName )->text()
			);
		}

		$listItems = '';
		foreach ( $posts as $post ) {
			$title = $post['title'];

			$link = Html::element(
				'a',
				[ 'href' => $title->getLocalURL() ],
				$title->getSubpageText()
			);

			$meta = Html::rawElement(
				'span',
				[ 'class' => 'userblogs-post-meta' ],
				wfMessage(
					'userblogs-listing-meta',
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

		return Html::rawElement(
			'div',
			[ 'class' => 'userblogs-listing' ],
			Html::rawElement(
				'ul',
				[ 'class' => 'userblogs-post-list userblogs-listing-list' ],
				$listItems
			)
		);
	}
}
