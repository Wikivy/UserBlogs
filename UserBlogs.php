<?php

/**
 * UserBlogs Extension
 * 
 * Adds blog functionality to MediaWiki with custom namespaces and privacy settings
 * 
 * @file
 * @ingroup Extensions
 * @author Wikivy
 * @license MIT
 */

if ( !defined( 'MEDIAWIKI' ) ) {
    die( 'This file is a MediaWiki extension and not a valid entry point' );
}

$wgExtensionCredits['specialpage'][] = [
    'path' => __FILE__,
    'name' => 'UserBlogs',
    'version' => '1.0.0',
    'author' => 'Wikivy',
    'url' => 'https://www.mediawiki.org/wiki/Extension:UserBlogs',
    'descriptionmsg' => 'userblogs-desc',
    'type' => 'specialpage',
];

// Define custom namespaces
define( 'NS_USER_BLOG', 200 );
define( 'NS_USER_BLOG_TALK', 201 );
define( 'NS_BLOG', 202 );
define( 'NS_BLOG_TALK', 203 );

$wgExtraNamespaces[NS_USER_BLOG] = 'User_blog';
$wgExtraNamespaces[NS_USER_BLOG_TALK] = 'User_blog_talk';
$wgExtraNamespaces[NS_BLOG] = 'Blog';
$wgExtraNamespaces[NS_BLOG_TALK] = 'Blog_talk';

$wgNamespacesWithSubpages[NS_USER_BLOG] = true;
$wgNamespacesWithSubpages[NS_BLOG] = true;

// Register special pages
$wgSpecialPages['CreateBlogPost'] = 'SpecialCreateBlogPost';
$wgSpecialPages['BlogSettings'] = 'SpecialBlogSettings';

// Register autoloader
$wgAutoloadClasses['SpecialCreateBlogPost'] = __DIR__ . '/specials/SpecialCreateBlogPost.php';
$wgAutoloadClasses['SpecialBlogSettings'] = __DIR__ . '/specials/SpecialBlogSettings.php';
$wgAutoloadClasses['UserBlogsHooks'] = __DIR__ . '/UserBlogs.hooks.php';
$wgAutoloadClasses['ApiBlogComment'] = __DIR__ . '/api/ApiBlogComment.php';

// Register hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'UserBlogsHooks::onLoadExtensionSchemaUpdates';
$wgHooks['BeforePageDisplay'][] = 'UserBlogsHooks::onBeforePageDisplay';
$wgHooks['SkinTemplateNavigation::Universal'][] = 'UserBlogsHooks::onSkinTemplateNavigation';

// Register API module
$wgAPIModules['blogcomment'] = 'ApiBlogComment';

// Extension messages
$wgMessagesDirs['UserBlogs'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['UserBlogsAlias'] = __DIR__ . '/UserBlogs.alias.php';

// Resource modules
$wgResourceModules['ext.userblogs'] = [
    'styles' => 'modules/ext.userblogs.css',
    'localBasePath' => __DIR__,
    'remoteExtPath' => 'UserBlogs',
];

$wgResourceModules['ext.userblogs.comments'] = [
    'scripts' => 'modules/ext.userblogs.comments.js',
    'styles' => 'modules/ext.userblogs.comments.css',
    'dependencies' => [ 'mediawiki.api', 'mediawiki.util' ],
    'localBasePath' => __DIR__,
    'remoteExtPath' => 'UserBlogs',
    'messages' => [
        'userblogs-comment-submit',
        'userblogs-comment-reply',
        'userblogs-comment-delete',
        'userblogs-comment-deleted',
        'userblogs-comment-error',
        'userblogs-comment-placeholder',
        'userblogs-must-login-comment',
        'userblogs-confirm-delete-comment'
    ]
];
