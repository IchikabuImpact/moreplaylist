# MorePlaylist

MorePlaylist is a web application built with the Slim PHP framework. It provides an interface for managing playlists with integration to Google APIs.

## Project Structure

### Root Directory
- `.gitignore`: Specifies files and directories that should be ignored by Git.
- `composer.json`: Contains the project dependencies and autoload configurations.
- `composer.lock`: Locks the versions of the project dependencies.
- `composer.phar`: PHP Archive for Composer.

### `application` Directory
Contains application-specific code and configurations.

### `public` Directory
Serves as the web root directory.
- `.htaccess`: Configures URL rewriting and other server settings.
- `about.html`: About page.
- `css/`: Contains CSS files.
- `favicon.ico`: Favicon for the website.
- `images/`: Contains image files.
- `index.php`: Main entry point of the application.
- `js/`: Contains JavaScript files.
- `privacy.html`: Privacy policy page.
- `term-of-use.html`: Terms of use page.

### `src` Directory
Contains the source code for the application.
- `Controller/`: Contains the application controllers.
- `routes.php`: Defines the routes for the application.

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/IchikabuImpact/moreplaylist.git
   ```

## JavaScript Test Setup (Characterization Tests)

This repo includes a Node-only test harness for front-end JavaScript characterization tests. The tests are designed to lock in current behavior before refactoring. Vitest + jsdom was selected because the project already uses ES modules and Vitest runs them natively with a lightweight jsdom environment. The tests run via a single command:

```bash
npm install
npm test
```

### JavaScript Inventory & Test Priorities

1. `public/js/VideoApp.js` — core app state, URL generation, fetch/playlist creation, DOM updates, pagination (highest risk/most logic).
2. `public/js/Main.js` — bootstrapping, API calls, playlist rendering, global event wiring.
3. `public/js/inline-scripts.js` — small DOM toggles for play/pause buttons.
4. Vendor/minified scripts (`jquery-3.6.0.min.js`, `cookieconsent.min.js`) — treated as external dependencies.

### JavaScript File Classification

**純粋関数（入力→出力で完結）**
- （該当なし。現状はDOM/通信/グローバル状態に依存）

**DOM操作中心（document/window依存）**
- `public/js/Main.js`
- `public/js/VideoApp.js`
- `public/js/inline-scripts.js`
- `public/js/jquery-3.6.0.min.js` (vendor)
- `public/js/cookieconsent.min.js` (vendor)

**通信中心（fetch/XHR依存）**
- `public/js/Main.js`
- `public/js/VideoApp.js`
- `public/js/jquery-3.6.0.min.js` (vendor ajax implementation)

**グローバル状態依存（window.* など）**
- `public/js/Main.js`
- `public/js/VideoApp.js`
- `public/js/jquery-3.6.0.min.js` (vendor)
- `public/js/cookieconsent.min.js` (vendor)

### How to Add More Tests

1. Add new tests under `tests/` using Vitest.
2. If testing DOM-heavy scripts that are not modules, load them with `tests/helpers/loadScript.js`.
3. Mock external effects:
   - `fetch` (network)
   - `$.ajax` / `$.get` (XHR)
   - `Math.random`, `Date`, etc. for determinism
4. Keep tests focused on current behavior; prefer “given input → output/DOM update” checks.

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.
