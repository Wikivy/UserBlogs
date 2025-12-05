CREATE TABLE /*_*/userblogs_comments (
	ubc_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	ubc_post_page_id INT UNSIGNED NOT NULL,
	ubc_parent_id INT UNSIGNED NULL,
	ubc_author INT UNSIGNED NOT NULL,
	ubc_created BINARY(14) NOT NULL,
	ubc_last_edited BINARY(14) NULL,
	ubc_last_edited_user INT UNSIGNED NULL,
	ubc_edit_count INT UNSIGNED NOT NULL DEFAULT 0,
	ubc_content MEDIUMTEXT NOT NULL,
	PRIMARY KEY (ubc_id),
	KEY ubc_post_page_id (ubc_post_page_id),
	KEY ubc_parent_id (ubc_parent_id),
	KEY ubc_author (ubc_author)
) /*$wgDBTableOptions*/;
