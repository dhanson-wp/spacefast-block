# Spacefast Block

A WordPress block that shows a [Spacefast](https://spacefast.com/) Space inline, full page, as a card, or as a link. The WordPress.org readme is in `readme.txt`.

## Requirements

- WordPress 6.7 or later
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
| `npm run plugin-zip` | Release zip, `spacefast-block.zip` |
| `composer lint` | PHP_CodeSniffer with WordPress standards |
| `composer test` | PHPUnit |

## Local site

The project folder is symlinked into a WordPress Studio site's `wp-content/plugins/` folder as `spacefast-block`, so a rebuild shows up on the site straight away. Activate the plugin from the Plugins screen, or with the Studio `wp_cli` tool.

## Project layout

```
spacefast-block.php   Plugin header, constants, block registration
uninstall.php         Removes stored keys, confirmed hosts, and cached lookups
includes/             PHP: URL rules, metadata, API client, connection, REST routes, takeover
src/space/            Block source: block.json, edit, render.php, view.js, styles
  components/         Editor UI (picker, inspector, previews)
  utils/              Shared JavaScript, with tests in utils/test
tests/php/            PHPUnit tests
build/                Build output (not tracked)
docs/                 Design and plan
```

## Releasing

Build first, then make the zip:

```
npm run build
npm run plugin-zip
```

That writes `spacefast-block.zip`, holding a `spacefast-block/` folder with the files named in the `files` list in `package.json`: `build/`, `includes/`, `src/` (without its tests), the main file, `uninstall.php`, `readme.txt`, and `LICENSE`. npm always adds `package.json` and this README too. `build/` isn't tracked, so the zip only has scripts if you built them first.

`.distignore` (for `wp dist-archive`) keeps tests, docs, and config out and keeps `src/` in, so a release carries the source of its scripts. `.gitattributes` does the same for GitHub archives, which also keep `package.json` and `package-lock.json` so they can be built.

## License

GPL-2.0-or-later. See `LICENSE`.
