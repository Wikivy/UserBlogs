<?php

namespace Wikivy\UserBlogs\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

class ReplyToCommentPresentationModel extends EchoEventPresentationModel
{

	public function getIconType()
	{
		return 'comment';
	}

	public function getPrimaryLink()
	{
		return $this->event->getTitle()->getLinkURL();
	}

	public function getHeaderMessage() {
		$msg = $this->msg( 'userblogs-notification-reply-to-comment' );
		$msg->params( $this->getAgentLink(), $this->event->getTitle()->getText() );
		return $msg;
	}

}
