=== Popularity Contest ===
Contributors: crowdfavorite, alexkingorg
Donate link: http://crowdfavorite.com/donate/
Tags: popular, popularity, feedback, view, comment, trackback, statistics, stats
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 2.1

Popularity Contest keeps a count of various views on your site and gives each one a 'popularity score' based on the number of views.

== Description ==

Links:

- [Wordpress.org Popularity Contest forum topics](http://wordpress.org/tags/popularity-contest?forum_id=10).
- [Popularity Contest plugin page at Crowd Favorite](http://crowdfavorite.com/wordpress/plugins/popularity-contest/).
- [Plugin forums at Crowd Favorite](http://crowdfavorite.com/forums/forum/popularity-contest/).
- [WordPress Help Center Popularity Contest support](http://wphelpcenter.com/plugins/popularity-contest/).

Which of your posts/pages are most popular?

Popularity Contest keeps a count of your post, category, tag and archive views, comments, trackbacks, etc. and uses them to determine which of your posts are most popular. There are numerical values assigned to each type of view and feedback; these are used to create a 'popularity score' for each post.

Using the weighted values assigned to each type of view and feedback, you can determine how the overall "popularity score" is calculated for each post. The values assigned to each view and feedback type can be changed at any time; once saved, the 'popularity score' for all posts are recalculated immediately to reflect the new values.

Not only is this information helpful to you, the site owner. But you can expose the most popular posts to your users through a widget or other template tags. This way they can visit your site and find the items that may be most reflective of your best work. If that's not the case, no worries, you can reset the values at any time.

Popularity contest also has the option to exclude post types and individual posts from the popularity calculation. So if you have one post skewing your data by a large amount, you can just add a meta value to the post, giving you more detailed popularity rankings of your other posts.

There are many template tags and shortcodes that Popularity Contest supports, view the FAQ or Usage tab in the Popularity Contest settings to understand how to use them.

== Installation == 

1. Download the plugin archive and expand it (you've likely already done this).
2. Upload the popularity-contest.php and transparent.gif file to your wp-content/plugins directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Popularity Contest. This will create the database table used by Popularity Contest.
4. Optional: go into Settings > Popularity (Popularity Values tab) to modify the values of each view and feedback type. 

== Frequently Asked Questions ==  

= Why are new posts so much more popular than old posts? =  

Since home and feed views have not been recorded for old posts, they won't be ranked as highly as new posts.

= Are pages and custom post types counted too? =

Yes, any publicly registered post type is counted. 

= How do I recount my comments/trackbacks =

If you have received comment spam or just need to recount your comments/trackbacks for any reason, you can use the 'Reset Comments/Trackback/Pingback Counts' button on the Settings > Popularity page.

= What if I want to re-enable Popularity Contest later? = 

No problem. Go back to the Plugins page and click 'Activate' for Popularity Contest. Popularity Contest will check to see if there are new posts and feedback since it was last activated, and will "catch up" as much as possible.

= How do I disable showing 'Popularity: n%' on my posts? = 

Disable this option on the settings page.

= How do I turn off the '[?]' on my posts? = 

Disable this option on the settings page.

= How do I not count posts by site authors? = 

Use the option on the settings page.

= How can I show lists of my most popular posts in my sidebar and theme? =

The easiest way is to use the included widgets with a widget-enabled theme.

There are template tags included in Popularity Contest to make it easy for you to show lists of your most popular posts. These tags, along with an explanation of how to use them, can be found on the Popularity Contest options page in your WordPress admin: Options > Popularity.

In addition, included is the legacy sidebar.php file (from the default template), that has contextual popular posts lists already added. When viewing a category, the included sidebar shows a list of most popular posts in that category. When viewing a month archive, the included sidebar shows a list of most popular posts for that month.

= Are there any shortcodes that can be used in post content? =

There are two short codes that can be used in your posts:

`[akpc_the_popularity]` will display the popularity of the current post.

`[akpc_most_popular]` will display the 10 most popular posts of your site, if you want to display a different number of posts use: `[akpc_most_popular limit='10']` replacing 10 with however many posts you want to list. 

*Note that these are not static, so using them in a post to get a snapshot of your site's posts' popularity at a given time will not work.

= Can I hide popularity on some post and not others? =

Yes, you can utilize the shortcodes above to do this. Or if you prefer to hide the popularity, add a custom field to the post/page as follows:
   name: hide_popularity
   value: 1

= Can I exclude certain post types from Popularity Contest? =

Yes, you can enable and disable post types on the Popularity Contest options page. Popularity Contest will continue to keep track of visits to those pages but will not use them in calculating popularity nor display their popularity.

= Can I exclude certain posts from Popularity Contest? =

Yes, you can add the following custom field to exclude any post from the popularity calculations (and display):
	name: exclude_from_popularity
	value: 1
	
Note that Popularity Contest will still keep track of the post's stats if you wish to remove this field later.

= Why don't my averages add up to 100%? =

Posts can include multiple categories, and home page views or category pages can include posts with multiple tags. So if a post with multiple categories or tags is viewed, it gets counted towards all of the categories it is in.

= Whats the difference between Most Popular Months and Average By Month =

Most popular months will calculate its percentage by summing up all the popularity for posts in that month and comparing that number with other months. Average By Month will take the average of each post in a month then compare that average with other months.

= Anything else? =

That about does it - enjoy!

== Screenshots ==

1. The admin section with various popularity listings.
2. Popularity values on the options page.
3. Popularity percentage listed on a post.

== Changelog ==

= 2.1 =
- New : Filter for output markup
- New : Shortcodes
- New : Nonces
- New : Added ability to filter by posts based on type
- New : Added meta for excluding certain posts from popularity calculation
- New : Averages for Tags, Categories and Months
- New : Support for non traditional directory structures
- New : Screenshots
- Changed : Using prepare, submit, insert for DB calls
- Changed : Added past tags to changelog
- Changed : Updated widget to utilize WP_Widget class
- Bugfix : Error of 'Object of class stdClass could not be converted to string' when resetting data
- Bugfix : MySQL queries were not properly getting the correct data
- Bugfix : Data wasn't being properly recorded for certain views
- Bugfix : Data will be recorded even without the API enabled
- Bugfix : Some excerpts would strip the tags from the JS API and displaying AKPC_IDS += "post_id"
- Bugfix : Sidebar and non-loop posts would be getting counted in the views
- Bugfix : Images in RSS feeds were being improperly displayed

= 2.0 =
- New : Now compatible with caching plugins.
- New : Support for tags and tag reports.
- New : Support for tracking search engine visitors differently than direct visitors.
- New : Option to ignore page views by site authors (your own actions on your site don't affect your popularity stats).
- New : Additional options so that there is no need to edit constants in the file directly.
- Changed : Restructuring and writing, lots of things have changed to work better with recent changes in WordPress.

= 1.3 =
- Changed : Modified SQL queries that fetch data
- Changed : Additional error message handling
- Bugfix : Minor bugfixing

= 1.2.1 = 
- Changed : More informative error messages

= 1.2 =
- New : Added function to recount feedback
- New : Support for pages
- Changed : Reformatted some settings
