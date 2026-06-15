# Kosher Comments

`Kosher Comments` is an enhanced commenting plugin for WordPress that replaces the default frontend comments template while keeping native WordPress comments as the source of truth.

It adds AI moderation, strikes and account locking, ratings, image uploads, reporting, analytics, branded admin screens, and a modern review-style UI on top of the built-in WordPress comment system.

## What It Does

- Uses native `wp_comments` for all comments and replies
- Replaces the default `comments_template()` output on singular posts
- Adds AI moderation before a comment is saved
- Gives users strikes for toxic comments
- Locks accounts after the configured strike threshold
- Blocks locked users from logging in
- Supports one rating per user per post
- Supports image uploads per comment
- Supports threaded replies
- Supports likes and dislikes
- Supports comment and image reporting
- Adds moderation tools for admins
- Adds analytics dashboards and post-level comment analytics

## Core Product Behavior

### Native WordPress Comments First

This plugin does not maintain a second comment store for the main comment content.

- New comments are created in `wp_comments`
- Replies are stored in `wp_comments`
- Existing WordPress comments are read directly from `wp_comments`
- Frontend edit/delete moderation actions operate on native WordPress comments

This keeps the system scalable and aligned with WordPress core.

### Plugin Data on Top of Native Comments

The plugin stores feature-specific data separately:

- Comment meta stores rating, question flag, reply notification preference, moderation reason, and location
- Custom tables store images, votes, reports, strikes, banned emails, and analytics events

That means WordPress remains the canonical comment layer, while Kosher Comments enhances it.

## Main Features

### 1. AI Moderation

Before a comment is saved, the plugin sends moderation context to OpenAI:

- `comment_text`
- whether it is a reply
- parent comment content when applicable

Expected moderation shape:

```json
{
  "is_toxic": true,
  "reason": "string"
}
```

If a comment is flagged:

- the comment is not inserted
- the user gets a strike
- the frontend returns an error
- the UI shows a misconduct warning

### 2. Strike System

Strikes are stored per user.

- configurable lock threshold
- locked users are blocked at login
- admins can reset strikes
- admins can unlock users
- admins can permanently ban emails

### 3. Ratings

Ratings are tied to comments, but limited to one rating per user per post.

- users can rate from 1 to 5 stars
- replies cannot rate
- if a user already rated that post, the rating picker is hidden
- the form shows `You rated this` and the saved stars instead

### 4. Questions

Top-level comments can be marked as questions.

- replies cannot be marked as questions
- question comments can trigger a notification to the post author

### 5. Images

Users can upload multiple images per comment.

- image count per comment is configurable
- max image size is configurable
- image URLs resolve through WordPress attachment URLs, which supports offloaded media/CDN setups
- comment images open in a modal slider
- slider navigation is grouped by comment, not by user

### 6. Interactions

Each comment supports:

- like
- dislike
- reply
- copy/share link
- report

Admins and editors also have frontend actions for:

- edit
- delete

### 7. Reporting and Moderation

Logged-in users can report:

- comments
- images

Reports include:

- subject
- short reason/comment

The moderation screen shows only open reports.

Available moderation actions:

- dismiss report
- remove comment
- remove image

### 8. Analytics

The plugin includes a branded analytics dashboard in wp-admin.

It tracks and displays:

- total comments
- ratings count
- average rating
- likes
- dislikes
- replies
- questions
- blocked comments
- rating distribution
- weekday distribution
- hourly activity
- recent momentum
- top locations
- top posts

There is also a post-level analytics metabox in the editor.

## Frontend Experience

The frontend is built to feel more like a modern review system than default WordPress comments.

It includes:

- review summary section
- rating breakdown bars
- featured reviews
- image strip
- branded post composer
- staged posting overlay
- toast notifications
- branded modal dialogs
- threaded replies
- share link highlighting
- image modal and slider

## Shortcodes

- `[kosher_comments]` renders the full review and thread experience
- `[kosher_comments_form]` renders the compose form only
- `[kosher-comments-form]` is supported as a hyphenated alias for the compose-only shortcode
- each shortcode also accepts `post_id`, for example `[kosher_comments_form post_id="123"]`

Posting uses a staged loading experience so users do not double-submit:

- `Preparing to post`
- `Comment audit in progress`
- `Final review and publishing`

## Admin Experience

The admin side is fully branded for Kosher Comments and includes:

- Moderation screen
- Analytics dashboard
- Settings screen
- Post editor analytics metabox

Design language follows the Kosher color system already implemented in the plugin.

## Settings

Current settings include:

- OpenAI API key
- moderation model
- moderation enabled toggle
- comments per page
- max images per comment
- max image size
- strike lock threshold
- fallback notification email

## Data Model

### Native WordPress Tables Used

- `wp_comments`
- `wp_commentmeta`

### Plugin Tables Used

- `wp_kc_comment_images`
- `wp_kc_comment_votes`
- `wp_kc_user_strikes`
- `wp_kc_banned_emails`
- `wp_kc_reports`
- `wp_kc_analytics`

Note:
The actual table prefix depends on the site’s WordPress database prefix.

## Important Implementation Notes

### Native Comment IDs

All plugin features key off the native WordPress `comment_ID`.

That means:

- reports reference WordPress comment IDs
- votes reference WordPress comment IDs
- images reference WordPress comment IDs
- analytics events can reference WordPress comment IDs

### Offloaded Media Support

Image rendering resolves through attachment APIs like:

- `wp_get_attachment_url()`
- `wp_get_attachment_image_url()`

This allows the plugin to work with offloaded media/CDN URLs such as:

`https://images.kosher.com/uploads/...`

### Analytics Strategy

Comment totals, replies, ratings, and activity are derived from native WordPress comments and comment meta.

Plugin-only interaction data such as votes and blocked moderation events are pulled from plugin tables.

## File Structure

```text
/kosher-comments
  /includes
  /admin
  /public
  /assets/js
  /assets/css
  kosher-comments.php
```

## Main PHP Components

- `kosher-comments.php`
  Bootstraps the plugin and loads all services.

- `includes/class-kc-comments.php`
  Main comment service for submission, rendering, voting, reports, images, and thread hydration.

- `includes/class-kc-analytics.php`
  Analytics aggregation and event tracking.

- `includes/class-kc-api.php`
  OpenAI moderation integration.

- `includes/class-kc-strikes.php`
  Strike, lock, and banned email management.

- `includes/class-kc-auth.php`
  Login lock enforcement and related auth behavior.

- `admin/class-kc-admin.php`
  Admin menus, settings, moderation actions, and analytics page wiring.

- `public/class-kc-public.php`
  Frontend assets, comment template replacement, and rendering entry points.

## Installation

1. Place the plugin in `wp-content/plugins/kosher-comments`
2. Activate it in WordPress
3. Open `Kosher Comments > Settings`
4. Add your OpenAI API key
5. Configure moderation and posting settings
6. Visit a singular post that uses `comments_template()`

The plugin will replace the default frontend comments template automatically on singular content.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- Valid OpenAI API key for AI moderation

## Current Scope

This plugin is built as a production-style enhanced comment layer for Kosher.com with:

- native WordPress comment compatibility
- modern review UX
- admin moderation tooling
- analytics visibility
- AI-assisted safety controls

## Maintenance Notes

When extending the plugin, prefer this rule:

- keep comments in WordPress core tables
- keep feature metadata in comment meta
- keep high-volume or feature-specific relational data in plugin tables

That preserves compatibility, performance, and long-term maintainability.
