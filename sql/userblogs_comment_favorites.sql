CREATE TABLE /*_*/userblogs_comment_favorites (
	ubf_comment_id INT UNSIGNED NOT NULL,
	ubf_user_id INT UNSIGNED NOT NULL,
	ubf_created BINARY(14) NOT NULL,
	PRIMARY KEY (ubf_comment_id, ubf_user_id),
	KEY ubf_user_id (ubf_user_id)
) /*$wgDBTableOptions*/;
