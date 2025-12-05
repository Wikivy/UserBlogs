CREATE TABLE /*_*/userblogs_posts (
	ubp_page_id INT UNSIGNED NOT NULL,
	ubp_user_id INT UNSIGNED NOT NULL,
	ubp_username VARBINARY(255) NOT NULL,
	ubp_created BINARY(14) NOT NULL,
	ubp_last_touched BINARY(14) NOT NULL,
	ubp_comment_count INT UNSIGNED NOT NULL DEFAULT 0,
	ubp_last_comment BINARY(14) NULL,
	ubp_last_comment_user INT UNSIGNED NULL,
	PRIMARY KEY (ubp_page_id),
	KEY ubp_user_id (ubp_user_id),
	KEY ubp_created (ubp_created)
) /*$wgDBTableOptions*/;
