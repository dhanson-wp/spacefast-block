# Spacefast Block

Show a [Spacefast](https://spacefast.com/) Space in a WordPress page or post, using the block editor.

## Description

Choose how your Space appears:

- **Inline:** a frame within your content, with height and aspect ratio controls.
- **Full page:** the Space fills the page, with optional site header and footer. This mode requires the Space to be the only block on the page.
- **Card:** a preview image, title, description, and link.
- **Link:** a button-style link to the Space.

Cards and links can open a Space in a modal that uses your theme's colors and fonts. Paste a public Space address, or connect your Spacefast account to search your Spaces. Private Spaces appear as cards or links; visitors sign in at Spacefast to access them.

## Requirements

- WordPress 6.7 or later
- PHP 7.4 or later
- A public Space for inline or full page display
- A Spacefast account if you want to search your Spaces from the editor

## Installation

1. Download **spacefast-block.zip** from the [latest release](https://github.com/dhanson-wp/spacefast-block/releases/latest).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose the ZIP, and install it.
3. Activate **Spacefast Block**.
4. Edit a page or post, add the **Spacefast** block, and paste a Space address or connect your account.

Use the attached release ZIP. GitHub's automatic **Source code** downloads don't include the compiled block files and must be built before installation.

## Frequently asked questions

### Do I need to connect my account?

You can paste a public Space address without connecting. Connecting lets you search your Spaces and retrieve private Space titles that your account can access.

### Can I show a private Space?

Private Spaces appear as cards or links, with an optional Private badge. They can't be displayed in a frame. Choose a preview image from your WordPress media library; visitors follow the link and sign in at Spacefast.

### Where are connection keys stored?

Each author's key is encrypted and stored with their WordPress user account. The plugin uses it on the server and doesn't put it in block content or send it to visitors. Disconnecting removes the locally stored key; revoke it in your Spacefast dashboard to invalidate it at Spacefast too.

## External services and privacy

The plugin contacts Spacefast for account connections, Space searches, and Space metadata. Frames and remote preview images make requests from visitors' browsers to their respective hosts. Full page display can also fetch Space content through your WordPress server.

See [readme.txt](readme.txt) for the full third-party service disclosure, terms and privacy links, troubleshooting, changelog, and upgrade notices. It is the canonical WordPress plugin directory readme and follows the [WordPress readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/).

## Development

Building from source requires Node.js and npm. Composer installs the PHP development tools.

### Setup

```
npm ci
composer install
npm run build
```

Use `npm start` to rebuild on change. The plugin only works after a build, because the block is registered from `build/space`. Without one, it shows an admin notice instead.

### Commands

| Command | What it does |
|---|---|
| `npm run build` | Production build into `build/` |
| `npm start` | Watch and rebuild |
| `npm run lint:js` | ESLint |
| `npm run lint:css` | Stylelint |
| `npm run test:unit` | Jest unit tests |
| `npm run plugin-zip` | Release zip, `spacefast-block.zip` |
| `composer lint` | PHP_CodeSniffer with WordPress standards |
| `composer test` | PHPUnit |

## Building a release

```sh
npm ci
npm run build
npm run plugin-zip
```

Install the resulting `spacefast-block.zip` to test the packaged plugin. The package includes compiled assets, their source, PHP files, plugin documentation, and the license. Dependencies, tests, and internal planning files are excluded.

## Support and contributions

Report reproducible bugs and suggest improvements in [GitHub issues](https://github.com/dhanson-wp/spacefast-block/issues). Include the plugin, WordPress, and PHP versions and the steps to reproduce the problem. Keep credentials and private Space addresses out of issues and pull requests.

## License

[GPL-2.0-or-later](LICENSE).
