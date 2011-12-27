=== Popularity Contest ===
Tags: popular, popularity, feedback, view, comment, trackback, statistics, stats
Contributors: alexkingorg, crowdfavorite
Requires at least: 2.3
Tested up to: 2.8
Stable tag: 2.0b2

== Description ==

Which of your posts/pages are most popular?

Popularity Contest keeps a count of your post, category and archive views, comments, trackbacks, etc. and uses them to determine which of your posts are most popular. There are numerical values assigned to each type of view and feedback; these are used to create a 'popularity score' for each post.

The values assigned to each view and feedback type are editable and can be changed at any time. When you change any of these values, the 'popularity score' for all posts are updated immediately to reflect the new values.


== Installation == 

1. Download the plugin archive and expand it (you've likely already done this).
2. Upload the popularity-contest directory to your wp-content/plugins directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' for Popularity Contest. This will create the database table used by Popularity Contest.
4. Congratulations, you've just installed Popularity Contest and it is now tracking data for you.
5. Optional: go into Settings > Popularity to modify the values of each view and feedback type. 


== Frequently Asked Questions ==  

= Why are new posts so much more popular than old posts? =  

Since home and feed views have not been recorded for old posts, they won't be ranked as highly as new posts.

= Are pages counted too? =

Yes, pages are counted too.

= How do I recount my comments/trackbacks =

If you have received comment spam or just need to recount your comments/trackbacks for any reason, you can use the 'Reset Comments/Trackback/Pingback Counts' button on the Settings > Popularity page.

= How do I uninstall Populairy Contest? = 

Go back to you Plugins page, and click 'Deactivate' for Popularity Contest. Note: this does not remove your popularity data.

= What if I want to re-enable Popularity Contest later? = 

No problem. Go back to the Plugins page and click 'Activate' for Popularity Contest. Popularity Contest will check to see if there are new posts and feedback since it was last activated, and will "catch up" as much as possible.

= How do I disable showing 'Popularity: n%' on my posts? = 

Disable this option on the settings page.

= How do I turn off the '[?]' on my posts? = 

Disable this option on the settings page.

= How do I not count posts by site authors? = 

Use the option on the settings page.

= How can I show lists of my most popular posts in my sidebar? =

The easiest way is to use the included widgets with a widget-enabled theme.

There are template tage included in Popularity Contest to make it easy for you to show lists of your most popular posts. These tags, along with an explanation of how to use them, can be found on the Popularity Contest options page in your WordPress adming: Options > Popularity.

In addition, I've included the legacy sidebar.php file (from the default template), that has contextual popular posts lists already added. When viewing a category, the included sidebar shows a list of most popular posts in that category. When viewing a month archive, the included sidebar shows a list of most popular posts for that month.

= Anything else? =

That about does it - enjoy!

--Alex King

http://alexking.org/projects/wordpress


== Changelog ==

= 2.0 =

- Pretty major rewrite, lots of things have changed to work better with recent changes in WordPress.
- Now compatible with caching plugins.
- Support for tags and tag reports.
- Support for tracking search engine visitors differently than direct visitors.
- Option to ignore page views by site authors (your own actions on your site don't affect your popularity stats).
- Additional options so that there is no need to edit constants in the file directly.
