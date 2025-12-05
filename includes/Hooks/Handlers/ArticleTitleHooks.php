<?php

namespace Wikivy\UserBlogs\Hooks\Handlers;

use MediaWiki\Page\Hook\ArticleFromTitleHook;
use Wikivy\UserBlogs\Pages\BlogPage;

class ArticleTitleHooks implements ArticleFromTitleHook
{

	public function onArticleFromTitle($title, &$article, $context)
	{
		wfDebugLog('UserBlogsArticleTitleHooks',"Article title hook triggered\n\n");

		/*if ($title->inNamespace(NS_BLOG) || $title->inNamespace(NS_USER_BLOG))
		{
			$out = $context->getOutput();

			$out->disableClientCache();

			$article = new BlogPage($title);
		}*/
	}
}
