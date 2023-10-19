# coworker: Development

## Tests

Tests are organized into two groups:

* `@group unit`: Basic tests that run without much environmental setup/support. To run this group:
    ```bash
    cd coworker
    phpunit8 --group unit
    ```
* `@group e2e`: End-to-end tests that require a working Civi instance (`CV_TEST_BUILD`). To run this group:
    ```bash
    cd coworker
    export CV_TEST_BUILD='/path/to/site/root'
    phpunit8 --group e2e
    ```
    <!-- Wishlist: Alow E2E testing on (cv || drush || wp-cli).  Take pipe-command instead of folder-path, and get everything you need via pipe. -->

(Note: `CV_TEST_BUILD` is required for E2E tests, and it is ignored by unit tests.)

# Publication

* New builds of `master` are published automatically by https://test.civicrm.org/view/Tools/job/Tool-Publish-coworker/ 
* The most recent successful build is `https://download.civicrm.org/coworker/coworker.phar`
* Historical builds for specific revisions are also available (eg `https://download.civicrm.org/coworker/coworker.phar-v0.1`; *naming per `git describe --tags`*)
