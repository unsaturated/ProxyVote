# Proxy Vote
Contributors: unsat
Author: Matthew M. Crumley
Tags: wordpress, quorum, proxy, plugin, voting, meetings, php
Requires at least: 2.6
Tested up to: 4.8
Stable tag: 1.1.0
Requires PHP: 5.2.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.paypal.me/unsaturated

Collects proxy voting information for small events.

## Description
Trying to reach a quorum at your meetings and the traditional methods aren't working? It's time to use the Proxy Vote plug-in for WordPress. Create voting events, customized flyers, and a page or post to collect the results.

## Installation
  1. Go to **Plugins** -> **Add New**
  2. Click on the **Upload Plugin** button and pick the ZIP file.
  3. Click the **Activate Plugin** button when the plugin has finished uploading.
  4. Go to **Settings** -> **Proxy Vote**; click the **Create Tables** button.
  5. Go to **Tools** -> **Proxy Vote** and you're ready to create an event.

## Features
  * Manage multiple, simultaneous events.
  * Works on old *and* new versions of WordPress: 2.6 through 4.8.
  * Each proxy event description can contain HTML, images, URL links, and more.
  * Event descriptions are formatted to print on individual pages.
  * Customize the form text then insert it with an entry like `[proxy2]`.
  * It works on posts and pages.
  * Information and error messages can be localized to another language very easily.
  * Submitted proxies record the IP address of the sender.
  * Events can be exported to an XML file.

## Anti-Features
  * Events cannot be re-imported to the database from the XML file. Arguably, what's the point when the event has expired?
  * The number of voters cannot be changed once an event is created. Expiration, title, and description _can_ be changed at any time.
  * There is no event start time, only an expiration time. In other words, do not post your event until you are ready to use it.

## Screenshots
 1. Adding an event
 2. Event details
 3. Event short code
 4. Posted and ready to collect proxies

## Changelog
### 1.1.0
 * Initial release
 * Available on GitHub

### 1.1.1
 * Add readme.txt per wordpress.org spec
 * Move screenshots to assets folder