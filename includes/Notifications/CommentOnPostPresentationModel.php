<?php

namespace Wikivy\UserBlogs\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

class CommentOnPostPresentationModel extends EchoEventPresentationModel
{
	public function getIconType()
	{
		return 'comment';
	}

	public function getPrimaryLink() {
		return $this->event->getTitle()->getLinkURL();
	}

	public function getHeaderMessage() {
		$msg = $this->msg( 'userblogs-notification-comment-on-post' );
		$msg->params( $this->getAgentLink(), $this->event->getTitle()->getText() );
		return $msg;
	}
}
