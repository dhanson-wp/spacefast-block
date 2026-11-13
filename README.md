# Spacefast Block

A WordPress block that shows a [Spacefast](https://spacefast.com/) Space inline, full page, as a card, or as a link. The WordPress.org readme is in `readme.txt`.

## Requirements

- WordPress 6.6 or later
- PHP 7.4 or later
- Node.js and npm, to build the editor and view scripts
- Composer, for PHP linting and tests

## Development setup

```
npm install
composer install
npm run build
```

Use `npm start` to rebuild on change. The plugin only works after a build, because the block is registered from `build/space`. Without one, it shows an admin notice instead.

## Scripts

| Command | What it does |
|---|---|
| `npm run build` | Production build into `build/` |
| `npm start` | Watch and rebuild |
| `npm run lint:js` | ESLint |
| `npm run lint:css` | Stylelint |
| `npm run test:unit` | Jest unit tests |
| `composer lint` | PHP_CodeSniffer with WordPress standards |
| `composer test` | PHPUnit |

## Local site

The project folder is symlinked into a WordPress Studio site's `wp-content/plugins/` folder as `spacefast-block`, so a rebuild shows up on the site straight away. Activate the plugin from the Plugins screen, or with the Studio `wp_cli` tool.

## Project layout

```
spacefast-block.php   Plugin header, constants, block registration
uninstall.php         Removes stored keys and cached lookups
includes/             PHP: URL rules, metadata, API client, connection, REST routes, takeover
src/space/            Block source: block.json, edit, render.php, view.js, styles
  components/         Editor UI (picker, inspector, previews)
  utils/              Shared JavaScript, with tests in utils/test
tests/php/            PHPUnit tests
build/                Build output (not tracked)
docs/                 Design and plan
```

## Releasing

`.distignore` lists what stays out of the release zip: source, tests, docs, and config. A release contains `build/`, `includes/`, `readme.txt`, `LICENSE`, `uninstall.php`, and the main file.

## License

GPL-2.0-or-later. See `LICENSE`.
