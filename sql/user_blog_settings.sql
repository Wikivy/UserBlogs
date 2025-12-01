CREATE TABLE IF NOT EXISTS /*_*/user_blog_settings (
    ubs_user_id INT PRIMARY KEY,
    ubs_is_public TINYINT(1) NOT NULL DEFAULT 1,
    INDEX ubs_is_public (ubs_is_public)
) /*$wgDBTableOptions*/;
