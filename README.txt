
=== Multisite Widgets Context ===
Contributors: nicholas_io
Donate link: 
Tags: multisite, widgets, widgets context
Requires at least: 4.1
Tested up to: 4.9.7
Stable tag: 1.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress Multisite Plugin that runs a Widget in a context of another site that belongs to the network

== Description ==

A WordPress Multisite Plugin that runs a Widget in a context of another site that belongs to the network

= How it works =
On every Widget, this plugin adds a select menu that you can choose on which site context you will execute the widget.
It also has a option to pull the widget data from target site, you can check this option if you want to populate the data of widget that depends of the context site, with content from the target site. See Screenshots to understand better.


== Installation ==

To install just follow the installation steps of most WordPress plugin's:

e.g.

1. Download the file multisite-widgets-context.zip;
2. Unzip the file on your computer;
3. Upload folder multisite-widgets-context, you just unzip to `/wp-content/plugins/` directory;
4. Activate the plugin on the `Plugins` menu in WordPress;
5. Be happy.

THIS PLUGIN ONLY WORKS WITH MULTISITE

== Frequently Asked Questions ==

= Can I use this plugins without multisite =

No, It does't make sense to use this plugins without multisite.

= I'm trying to run a shortcode in a context of another site and it's not working. =
As switch_to_blog does not switch plugins or theme, you need the code for shortcode running on the site that you are EXECUTING THE WIDGET. If the shortcode is provided by a plugin, just activate the plugin on the site which you want to run the widget.

== Screenshots ==


1. A Recent Posts Widget configured to execute on another site context.

2. A Nav Menu Widget with select populated with data from target site due to checked checkbox

== Changelog ==

= 1.1.2 =
* Fixing a bug where a PHP Fatal error could happen on the append_current_site_context() method.

= 1.1.1 =
* Fixing a bug where the selected site context wasn't being persisted in some edge cases


= 1.1.0 =
* WordPress 4.5 Compatibility
* Code Refactoring to better meet WordPress standards
* Security enhancements: escaping and sanitization
* Performance Improvements: Caching get_blog_ids method


= 1.0.3 =
* Fixed a bug when a new widget is added to a sidebar and the current site isn't checked by default
* WordPress 4.3 compatibility


== Contribute ==

You can contribute to the source code in our [GitHub](https://github.com/nicholasio/multisite-widgets-context) page.