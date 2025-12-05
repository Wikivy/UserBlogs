CREATE TABLE /*_*/userblogs_comment_likes (
	ubl_comment_id INT UNSIGNED NOT NULL,
	ubl_user_id INT UNSIGNED NOT NULL,
	ubl_created BINARY(14) NOT NULL,
	PRIMARY KEY (ubl_comment_id, ubl_user_id),
	KEY ubl_user_id (ubl_user_id)
) /*$wgDBTableOptions*/;
