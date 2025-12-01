CREATE TABLE IF NOT EXISTS /*_*/user_blog_comments (
    ubc_id INT PRIMARY KEY AUTO_INCREMENT,
    ubc_page_id INT NOT NULL,
    ubc_user_id INT NOT NULL,
    ubc_parent_id INT NOT NULL DEFAULT 0,
    ubc_content TEXT NOT NULL,
    ubc_timestamp BINARY(14) NOT NULL,
    INDEX ubc_page_id (ubc_page_id),
    INDEX ubc_parent_id (ubc_parent_id),
    INDEX ubc_timestamp (ubc_timestamp)
) /*$wgDBTableOptions*/;
