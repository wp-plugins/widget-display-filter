=== Widget Display Filter ===
Contributors: enomoto celtislab
Tags: widgets, hide, filter, conditional tags, widget logic
Requires at least: 3.9
Tested up to: 4.2
Stable tag: 1.0.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Set the display condition for each widget. Widgets display condition setting can be easily, and very easy-to-use plugin.

== Description ==

It defines Hashtags that are associated with the display conditions. and use Hashtag to manage the display conditions of the widget. By setting the same Hashtag to multiple widgets, you can easily manage as a group. (
Of course, Hashtag does not appear at run time.)

Feature

 * Support Device filter (Discrimination of Desktop / Mobile device uses the wp_is_mobile function)
 * Support Post Format type filter
 * Support Post category and tags filter
 * Support Custom Post type filter

Usage

1. Open the menu - "Appearance -> Widget Display Filter", and configure and manage the display conditions of Widgets.
2. Definition of Hashtags associated with the widget display conditions.
3. Open the menu - "Appearance -> Widgets", and set the display condition for each widget.
4. If you enter Hashtag in Widget Title input field, its display condition is enabled.

Notice

 * Hashtag that can be set for each widget is only one. 
 * Between Hashtag and title should be separated by a space.


[日本語の説明](http://celtislab.net/wp_widget_display_filter/ "Documentation in Japanese")

== Installation ==

1. Upload the `widget-display-filter` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress
3. Set up from `Widget Display Filter` to be added to the Appearance menu of Admin mode.

Note

 * This plugin is required PHP 5.3 or higher
 * This plugin only single-site.

== Screenshots ==

1. Registration of 'Hidden Widgets'.
2. When you register to 'Hidden Widgets', it does not appear in 'Available Widgets'.
3. Using Hashtag, Make the settings for the display conditions of Widgets.
4. Set the display condition by Post ID.
5. Set the display conditions by post Category.
6. Set the display conditions by post Tag.
7. Setting an example to the widget.

== Changelog ==

= 1.0.0 =
* 2015-08-05  Release
 
