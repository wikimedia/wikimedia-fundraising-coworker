# coworker: Development

## Inspection

Consider setting these [config options](config.md):

```bash
coworker debug -d logPolling=1 -d logInternalQueue=1
```

## Tests

Tests are organized into two groups:

* `@group unit`: Basic tests that run without much environmental setup/support. To run this group:
    ```bash
    cd coworker
    phpunit9 --group unit
    ```
* `@group e2e`: End-to-end tests that require a working Civi instance (`CV_TEST_BUILD`). To run this group:
    ```bash
    cd coworker
    export CV_TEST_BUILD='/path/to/site/root'
    phpunit9 --group e2e
    ```
    <!-- Wishlist: Alow E2E testing on (cv || drush || wp-cli).  Take pipe-command instead of folder-path, and get everything you need via pipe. -->

(Note: `CV_TEST_BUILD` is required for E2E tests, and it is ignored by unit tests.)

Release Process
===============

For pre-releases, the Jenkins job https://test.civicrm.org/view/Tools/job/Tool-Publish-civix
will automatically publish to `https://download.civicrm.org/coworker/coworker-EDGE.phar`

For the official releases, the process requires:

* Google Cloud CLI tools (with authentication and suitable permissions)
	<!-- gcloud cli has login command that should be sufficient -->
<!-- * Github CLI tools (with authentication and suitable permissions) --><!-- you can create personal developer API key in github web UI -->
* GPG (with appropriate private key loaded; e.g. `7A1E75CB`)
* Nix

Then, on a suitably configured host:

```bash
cd coworker
git checkout master
git pull

## Open subshell with suitable versions of most tools
nix-shell

## Do a dry-run -- Preview what will happen
./scripts/releaser.php release <VERSION> --dry-run

## Perform the actual release
./scripts/releaser.php release <VERSION>
```
