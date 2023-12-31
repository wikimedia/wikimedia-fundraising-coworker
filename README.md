# coworker

> __This project is in an early/pre-release phase.  The README gives a
> general overview of scope, but it may be out-of-sync with the code and
> depend on a mix of merged+unmerged core patches.__

Civi `coworker` is a task runner for CiviCRM -- it allows CiviCRM to run tasks in the background.

## Benefits

There are other paradigms for background processing in CiviCRM. `coworker` is distinct in *simultaneously* these features:

* __Multitasking__: `coworker` supports parallel execution of multiples tasks from multiple queues.
* __Generic__: `coworker` can execute diverse tasks, as defined by CiviCRM extensions. The sysadmin is not required to manually setup bespoke/per-task runners.
* __Compatible__: `coworker` is compatible with several deployment topologies - such as dedicated hosts, cloud hosts, and local hosts.
  Coworker can use local processes, SSH connections, and/or HTTP(S) connections.
* __Performant__: `coworker` can execute tasks with minimal delay (seconds or milliseconds - rather than minutes or hours).
* __Progressive enhancement__: `coworker` is compatible with almost any CiviCRM deployment type, but it gets progressively better if the deployment's technology permits.
* __Resource limits__: `coworker` respects multiple resource limits, such as #workers, worker-lifetime, and #requests-per-worker.
* __Redundancy__: A single CiviCRM deployment can have multiple `coworker`s. If one goes offline, the others continue.

## Requirements

* Required: PHP v7.2+
* Required: CiviCRM 5.51
* Recommended: `cv`, `drush`, or `wp-cli`

## Download

Download `coworker` as a system-wide utility:

```
sudo wget 'https://download.civicrm.org/coworker/coworker.phar' -O '/usr/local/bin/coworker'
sudo chmod +x /usr/local/bin/coworker
```

Download `coworker` as an add-on for your existing `composer` (D8+) project:

```javascript
{
    "require": {
        "civicrm/composer-downloads-plugin": "~2.1|^3",
    },
    "extra": {
        "downloads": {
            "coworker": {
                "version": "F.I.X.M.E",
                "url": "https://download.civicrm.org/coworker/coworker.phar-{$version}.phar",
                "path": "bin/coworker",
                "type": "phar"
            }
        }
    }
}
```

Download `coworker` for patching or development:

```
git clone https://lab.civicrm.org/dev/coworker.git
cd coworker
composer install
```

## Connections

The `coworker run` command connects to CiviCRM, requests pending tasks, and executes them. This requires a communication channel, and each communication
channel has different properties:

| Communication Channel | Description | Compatibility | Latency | RAM/CPU Limits |
| -- | -- | -- | -- | -- |
| `web` | Send HTTP requests to a remote CiviCRM web server. | All web servers | Medium-high latency | Assigned by web-server |
| `pipe` (local) | Send bidirectional messages to a long-running CiviCRM process. | Servers with full sysadmin access | Low latency | Configurable |
| `pipe` (SSH/etc) | Send bidirectional messages to a long-running CiviCRM process. | Remote servers with SSH access (or similar) | Mixed (high-latency setup; low-latency message) | Configurable |

* __Compatibility__: Which CiviCRM deployments can use this type of connection?
* __Latency__: How quickly do tasks start when using this type of connection?
* __RAM/CPU Limits__: How are limits set for tasks?

## Usage

### Usage: HTTP

To run tasks remotely using HTTP:

```
coworker run --web='https://user:pass@example.com/civicrm/queue'
```

(*FIXME: discuss credentials management, authx, etc; maybe rework as pure JWT?*)

This method comes with some trade-offs:

* __Strengths__: Works with any web server architecture. If you have multiple web servers, requests will be automatically distributed among them.
* __Weaknesses__: To avoid overloading the web server, polling intervals are fairly long. Every request requires a full bootstrap. The
  resource limits (RAM and CPU time) for web servers are often a bit tight.

### Usage: Pipe

In Unix-style systems, a *pipe* is a flexible and performant mechanism for exchanging data and commands. To allow quicker processing of background tasks,
Coworker may start a CiviCRM process and exchange data through a pipe. However, the setup process may require greater access and more steps.

* __Strengths__: Once a pipe is started, it can be used repeatedly - which enables faster polling and reduced latency. Piped processes may be
  allowed to use additional resources (RAM and CPU time) that are unavailable to web requests. Piped processes use a pooling strategy that
  is optimized for background work.
* __Weaknesses__: Setup requires higher level of sysadmin access (minimally, SSH access; ideally, `sudo` or `root` access).

There are several ways to start `coworker` with a CiviCRM pipe.  These may use [cv](https://github.com/civicrm/cv), [drush](https://drush.org), or
[wp-cli](https://wp-cli.org/), as in:

```bash
## Start with cv
cd /var/www/example.com/web
coworker run --pipe='cv ev "Civi::pipe();"'

## Start with drush
cd /var/www/example.com/web
coworker run --pipe='drush ev "civicrm_initialize(); Civi::pipe();"'

## Start with wp-cli
cd /var/www/example.com/web
coworker run --pipe='wp eval "civicrm_initialize(); Civi::pipe();"'
```

There is a common theme in the examples: the `--pipe` parameter specifies a shell command, and the shell command ultimately invokes PHP's `Civi::pipe()`.  As long as the
end result is `Civi::pipe()`, you can develop many more variants of these commands -- allowing support for environment variables, CMS multisite, remote SSH connections,
and so on.  Here are a few more examples of `--pipe` commands:

```bash
## Use cv with multisite
HTTP_HOST=myvhost.example.com cv ev "Civi::pipe();"

## Use drush with multisite
drush -l myvhost.example.com ev "civicrm_initialize(); Civi::pipe();"

## Use wp-cli with multisite
wp --url=myvhost.example.com eval "civicrm_initialize(); Civi::pipe();"

## Use ssh to call cv remotely
ssh webuser@backend.example.com cv ev --cwd=/var/www/example.com/web ev "Civi::pipe();"
```

How do you know if the `--pipe` command is appropriate for your system? Run it manually. If it's working, it will prompt you to send/receive JSON:

```
$ cd /var/www/example.com/web
$ cv ev 'Civi::pipe();'
< ["Civi::pipe","5.50.1"]
> ["PROTO","1.0"]
< ["OK"]
> ["QUIT"]
```

## Usage: Hybrid Pipe-HTTP

In this example, we monitor for new tasks with a long-running SSH pipe, and then
execute specific tasks with medium-latency HTTPS requests.

```bash
coworker run --channel=pipe,web \
  --pipe='ssh webuser@backend.example.com cv ev --cwd=/var/www/example.com/web ev "Civi::pipe();"' \
  --web='https://user:pass@example.com/civicrm/queue'
```

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

# Configuration

The `Configuration` class defines a series of configuration options which may be defined in multiple ways:

| Class Property | File Field | Environment Variable | CLI Option |
| -- | -- | -- | -- |
| `$minimumCivicrmVersion` | `minimumCivicrmVersion` | n/a                        | `-d minimumCivicrmVersion=X` |
| `$maxConcurrentWorkers`  | `maxConcurrentWorkers`  | `COWORKER_MAX_WORKERS`     | `-d maxConcurrentWorkers=X` |
| `$maxTotalDuration`      | `maxTotalDuration`      | `COWORKER_MAX_DURATION`    | `-d maxTotalDuration=X` |
| `$maxWorkerRequests`     | `maxWorkerRequests`     | `COWORKER_WORKER_REQUESTS` | `-d maxWorkerRequests=X` |
| `$maxWorkerDuration`     | `maxWorkerDuration`     | `COWORKER_WORKER_DURATION` | `-d maxWorkerDuration=X` |
| `$maxWorkerIdle`         | `maxWorkerIdle`         | `COWORKER_WORKER_IDLE`     | `-d maxWorkerIdle=X`     |
| `$gcWorkers`             | `gcWorkers`             | `COWORKER_GC_WORKERS`      | `-d gcWorkers=X` |
| `$pipeCommand`           | `pipeCommand`           | n/a                        | `--pipe=X` |
| `$logFile`               | `logFile`               | n/a                        | `--log=X` |
| `$logLevel`              | `logLevel`              | n/a                        | `-v` or `-vv` |
| `$logFormat`             | `logFormat`             | n/a                        | `-d logFormat=X` |

If the same value is specified multiple ways, the value will be chosen based on priority (*from highest to lowest*):

* Command line option
* Environment variable
* Configuration file
* Class default

# Publication

* New builds of `master` are published automatically by https://test.civicrm.org/view/Tools/job/Tool-Publish-coworker/ 
* The most recent successful build is `https://download.civicrm.org/coworker/coworker.phar`
* Historical builds for specific revisions are also available (eg `https://download.civicrm.org/coworker/coworker.phar-v0.1`; *naming per `git describe --tags`*)

# Known limitations

* If you are adding `coworker` into an existing `composer` project (eg Drupal 8+), it is conceivable to download via
  `composer require civicrm/coworker`.  However, this technique may not serve you well in the long-run.  Why?
  Internally, `coworker` is built with [ReactPHP](https://reactphp.org) - which is an excellent framework for juggling
  concurrent tasks, but it is qualitatively very different from a traditional PHP application (like Drupal).  To
  minimize dependency-conflicts and confusion, one should keep a clear, clean separation between these frameworks.  The
  techniques described earlier ("[Download](#download)") strike a balance: separating the frameworks while also allowing
  interoperability and co-deployment.
    * _Idea: If someone really wants the benefits of `composer require`/`composer update`, then
      please look at setting up a bridge-project, eg `composer require civicrm/coworker-phar-drupal`.
      A bridge-project would download the PHAR, add a wrapper in `vendor/bin/coworker`, and change the
      default configuration to use a drush pipe._
