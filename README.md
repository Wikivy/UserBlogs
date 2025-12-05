# UserBlogs

**UserBlogs** is a lightweight, Fandom-style blog system for MediaWiki.

It provides:

- A `User_blog:` namespace for user blog posts
- A `Blogs:` namespace for global blog indexes
- Per-user blog index pages
- A **Blogs:Recent_posts** index
- Threaded comments on blog posts
- Comment likes and author favorites (YouTube-style “creator hearts”)
- REST API for posts and comments
- Optional Echo + email notifications for new comments and replies

It’s designed to be clean, predictable, and friendly to external clients (Android apps, SPAs, etc.), using **wikitext** for content and structured DB tables for metadata.

---

## Features

### Namespaces

- `User_blog:` (`NS_USER_BLOG`)
  - `User_blog:Username/Post title` – individual blog post
  - `User_blog:Username` – per-user blog index (auto-generated)
- `Blogs:` (`NS_BLOG`)
  - `Blog:Recent_posts` – global recent posts index (auto-generated)

### Blog posts

- Blog posts are standard wiki pages in `User_blog:`
- `UserBlogs` tracks metadata in `userblogs_posts`:
  - Author, created timestamp
  - Comment count
  - Last comment user + timestamp
- `User_blog:Username` automatically lists all posts for that user
- `Blogs:Recent_posts` lists latest posts across the wiki

### Comments

- Threaded comments for each blog post
- Stored in `userblogs_comments` (not separate pages)
- Fields include:
  - `ubc_post_page_id`, `ubc_parent_id` (for threading)
  - `ubc_author`, `ubc_created`
  - `ubc_content` (wikitext)
  - Edit metadata (last edited, edit count)
- Comments are rendered below blog posts and exposed via REST

### Likes & favorites

- **Likes**:
  - Any logged-in user can like/unlike a comment
  - Stored in `userblogs_comment_likes`
- **Favorites** (creator feature):
  - Only the **post author** can favorite/unfavorite comments
  - Works like YouTube’s “creator heart”
  - Stored in `userblogs_comment_favorites`
  - Exposed via REST and `getCommentsForPost()` with flags:
    - `likes`
    - `favoritedByPostAuthor`

### Notifications (Echo/email)

If [Echo](https://www.mediawiki.org/wiki/Extension:Echo) is installed and enabled:

- When someone comments on your blog post:
  - You receive `userblogs-comment-on-post` notification
- When someone replies to your comment on a blog post:
  - You receive `userblogs-reply-to-comment` notification

These can be delivered as:

- On-wiki notifications
- Email notifications (configurable via Echo prefs)
