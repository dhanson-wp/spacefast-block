=== Spacefast Block ===
Contributors: derekhanson
Tags: spacefast, block, app, prototype, iframe
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show any Spacefast Space inline, full page, as a card, or as a link.

== Description ==

Spacefast Block adds one block, "Spacefast", that puts a Space from [Spacefast](https://spacefast.com/) into a page or post. It uses core block controls for alignment, spacing, borders, color, and shadow, so it fits the rest of your content.

**Four ways to show a Space**

* **Inline** shows the Space in a frame in the flow of the page. Set a minimum height or an aspect ratio, and turn on a title bar if you want one.
* **Full page** lets the Space take over the page. You can keep or hide the site header and footer.
* **Card** shows the Space's title, description, and preview image as a card. Choose the image position and which parts to show.
* **Link** shows a button-style link to the Space.

Cards and links can open the Space in a near-full-screen modal that picks up your theme's colors and fonts, or go straight to the Space.

**Choosing a Space**

Paste the address of any public Space, or connect your Spacefast account in the editor and search the Spaces you can reach across all of your teams. Connecting uses Spacefast's device sign-in; you approve the connection on spacefast.com and nothing is typed into WordPress.

**Private Spaces**

A private Space can't be shown in a frame, so the block shows it as a card or a link, with a "Private" badge and a "Request access" link. Pasting a private Space's address works without connecting your account. Visitors sign in at Spacefast to see it. The badge text and the call to action are editable, and you can hide the badge.

**Branding**

A small Spacefast mark appears with the block. A toggle in the block settings turns it off.

== Installation ==

1. Upload the `spacefast-block` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen in WordPress.
2. Activate the plugin.
3. Edit a page or post, add the "Spacefast" block, and paste a Space address or connect your account.

== Frequently asked questions ==

= Does the Space need to be public? =

For inline and full page display, yes. A public Space can be shown in a frame. A private Space can only be shown as a card or a link.

= What happens to private Spaces? =

The block shows them as a card or a link with a "Private" badge. Visitors who follow the link sign in at Spacefast, and can request access if they don't have it. A pasted private address works without connecting your account. If you choose the Space from your account's search instead, you can also upload its thumbnail to the media library and show it on the card.

= Is my Spacefast key shared with visitors or other site users? =

No. The key is stored encrypted against your own user account, is used only on the server, and is never sent to the browser, saved in the block, or printed on a page. Other users of the site can't use your connection; each person connects their own account. Disconnecting removes the key from this site, and deleting the plugin removes every stored key. The key itself stays valid at Spacefast until it expires (after 30 days, or 7 days without use) unless you revoke it in your Spacefast dashboard.

= What happens if a Space later goes private? =

The block remembers whether a Space could be framed when you chose it, so an inline or full page block will show a blocked frame to visitors. Select the block and choose "Refresh details" in its toolbar; the block then switches to a card or a link. Nothing else on the page is affected.

= Why does Escape not close the modal while I'm using the Space? =

The Space runs inside a frame, and keystrokes inside a frame belong to the Space. While focus is inside it, Escape goes to the Space, not to your page. Click outside the Space, on the dark area around the modal, or use the close button. Press Escape once focus is back on the modal's own header.

= Can I use a Space on a custom domain? =

Yes. The block accepts any address that answers as a Spacefast Space, including custom domains. The site only frames a custom domain after the editor has confirmed it's a Space, which happens when you paste the address or choose "Refresh details". Until then, the block shows it as a card or a link.

== Third-party services ==

This plugin connects to Spacefast, and to whatever host a Space names for its preview image. For Spacefast's automatic screenshots, that host is s0.wp.com, WordPress.com's mShots service. The plugin sends nothing about your visitors.

**Spacefast API (api.spacefast.com)**

Used only in the editor, by signed-in users who can edit posts. It's contacted when an author connects their account, searches their Spaces, or asks the site to save the thumbnail of a private Space to the media library. The data sent is the author's Spacefast API key (as an authorization header, to api.spacefast.com only) and the search text or Space ID the author entered. For a private Space's thumbnail, api.spacefast.com may redirect the request to another host to get the image. The key is never sent to that other host. Visitors never trigger these requests. The requests identify themselves with a `spacefast-block/<version>` user agent.

* Terms of service: https://automattic.com/tos/
* Privacy policy: https://automattic.com/privacy/

(These are the terms and privacy links in the footer of https://spacefast.com/.)

**The Space's own address (Spacefast-hosted domains and custom domains)**

* When an author pastes or refreshes a Space address in the editor, your server requests that address to read the Space's title, description, and preview image. Results are cached for ten minutes. The request identifies itself with a `spacefast-block/<version>` user agent and sends no site or user details.
* When a page with an inline or full page block, or an open modal, is viewed, the visitor's browser loads the Space in a frame from the Space's own domain. The visitor's browser makes that request to Spacefast or to the Space's custom domain, as it would for any frame.

Terms and privacy policy: see the links above, and https://spacefast.com/.

**Preview images**

* A card shows the preview image the Space publishes. The visitor's browser loads it from that address, which can be the Space's own domain, s0.wp.com, or any other host the Space chose.
* When a Space has no preview image of its own, Spacefast publishes an automatic screenshot address on s0.wp.com (mShots, run by Automattic). In that case the visitor's browser requests the image from s0.wp.com, and the request includes the Space's address. Privacy policy: https://automattic.com/privacy/
* If the author saves the image to the media library, WordPress serves it from your own site, and when visitors view it, no outside host is contacted. To save a preview image from an address, your server downloads it from that address, sending no site or user details; for a private Space, the thumbnail comes from api.spacefast.com as described above.

== Changelog ==

= 0.1.0 =
* First release.

== Upgrade notice ==

= 0.1.0 =
First release.
