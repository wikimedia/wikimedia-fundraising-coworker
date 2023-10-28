# coworker: Download/Install

## Requirements

* Required: PHP v7.3+
* Required: CiviCRM 5.51
* Suggested: `cv`, `drush`, or `wp-cli`

<a name="urls"></a>
## Download URLs for alternate versions

| Format            | Version(s)           | URLs |
| --                | --                   | --   |
| Executable binary | Latest               | PHAR: https://download.civicrm.org/coworker/coworker.phar<br/>GPG: https://download.civicrm.org/coworker/coworker.phar.asc<br/>SHA256: https://download.civicrm.org/coworker/coworker.SHA256SUMS |
|                   | Edge (*autobuild*)   | PHAR: https://download.civicrm.org/coworker/coworker-EDGE.phar<br/>Logs: https://test.civicrm.org/view/Tools/job/Tool-Publish-coworker/ |
|                   | Historical           | PHAR: `https://download.civicrm.org/coworker/coworker-X.Y.phar`<br/>GPG: `https://download.civicrm.org/coworker/coworker-X.Y.phar.asc`<br/>SHA256: `https://download.civicrm.org/coworker/coworker-X.Y.SHA256SUMS*) |
| Source code       | All versions         | Git: https:/lab.civicrm.org/dev/coworker |

<a name="phar-unix"></a>
## Install `coworker.phar` as system-wide tool (Linux/BSD/macOS)

You may place the file directly in `/usr/local/bin`:

```
sudo curl -LsS 'https://download.civicrm.org/coworker/coworker.phar' -o '/usr/local/bin/coworker'
sudo chmod +x /usr/local/bin/coworker
```

<a name="phar-composer"></a>
## Install `coworker.phar` as project tool (composer)

If you are developing a web-project with [`composer`](https://getcomposer.org) (e.g.  Drupal 8/9/10) and wish to add `coworker.phar` to your project,
then use the [civicrm/cli-tools](https://github.com/civicrm/civicrm-cli-tools).

```bash
composer require civicrm/cli-tools
```

This adds CLI tools in [composer's `vendor/bin` folder](https://getcomposer.org/doc/articles/vendor-binaries.md).

You can call commands through `composer exec` or `vendor/bin`:

```bash
## Example 1: Call coworker through `composer exec`
composer exec coworker run

## Example 2: Call coworker through `./vendor/bin`
./vendor/bin/coworker run

## Example 3: Add coworker your PATH
PATH="/path/to/vendor/bin:$PATH"
coworker run
```

(*Alternatively, if you prefer to pick a specific version of each tool, then use [composer-downloads-plugin](https://github.com/civicrm/composer-downloads-plugin)
to download the specific PHAR release.*)

<a name="src-unix"></a>
## Install `coworker.git` as system-wide tool (Linux/BSD/macOS)

To download the source tree and all dependencies, use [`git`](https://git-scm.com) and [`composer`](https://getcomposer.org/).
For example, you might download to `$HOME/src/coworker`:

```bash
git clone https://lab.civicrm.org/dev/coworker $HOME/src/coworker
cd $HOME/src/coworker
composer install
./bin/coworker --help
```

You may then add `$HOME/src/coworker/bin` to your `PATH`. The command will be available in other folders:

```bash
export PATH="$HOME/src/coworker/bin:$PATH"
cd /var/www/example.com/
coworker api3 System.get | less
```

__TIP__: If your web-site uses Symfony components (as in D8/9/10), then you may see dependency-conflicts. You can resolve these by [building a custom PHAR](develop.md).
