<?php

namespace Wikivy\UserBlogs\Specials;

use MediaWiki\SpecialPage\RedirectSpecialPage;
use MediaWiki\Title\Title;

class SpecialMyBlog extends RedirectSpecialPage
{

	protected $mAllowedRedirectParams = [
		'action' , 'preload' , 'editintro', 'section'
	];

	function __construct()
	{
		parent::__construct( 'MyBlog' );
	}

	function getRedirect($subpage): bool|Title
	{
		global $wgUser;

		if ( strval( $subpage ) !== '' ) {
			return Title::makeTitle( NS_USER_BLOG, $wgUser->getName() . '/' . $subpage );
		} else {
			return Title::makeTitle( NS_USER_BLOG, $wgUser->getName() );
		}
	}
}
