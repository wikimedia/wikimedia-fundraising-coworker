# coworker: Configuration

The coworker `Configuration` describes all options for fine-tuning coworkers behavior. It governs how long
CiviCRM processes live and how much work they can do. Configuration options can be set in the follows ways:

1. Command line option (*highest priority*)
2. Environment variable
3. Configuration file (YAML/JSON)
4. Class default (*lowest priority*)

## File format

Create a YAML file such as `/etc/coworker.yaml`:

```yaml
workerCount: 5
workerDuration: 120
workerRequests: 10
workerTimeout: 15
```

To use this file, pass the `-c` option:

```bash
coworker run -c /etc/coworker.yaml
```

> __TIP__: At time of writing, coworker does not read any files by default. It will only read a file if instructed to do so.

## Environment variables

Similarly, you can set options before calling the command:

```bash
export COWORKER_COUNT=5
export COWORKER_DURATION=120
export COWORKER_REQUESTS=10
export COWORKER_TIMEOUT=15
coworker run
```

## Options: Connectivity

Coworker must send requests to CiviCRM. These options determine how it transmits requests.

### civicrmVersion

* __Description__: Only run if the local CiviCRM deployment meets this minimum requirement.
* __CLI Option__: `-d civicrmVersion=X`
* __Environment Variable__: _n/a_
* __File Field__: `civicrmVersion`
* __Class Property__: `$civicrmVersion`

### pipeCommand

External command used to start the pipe.

The special value `MINIPIPE` indicate a built-in adapter (based on `cv-lib`).

* __CLI Option__: `--pipe=X`
* __Environment Variable__: _n/a_
* __File Field__: `pipeCommand`
* __Class Property__: `$pipeCommand`

## Options: Polling

Coworker runs a control process to monitor CiviCRM. These options determine how it monitors.

## pollInterval

* __Description__: How often are we allowed to poll the queues for new items? (#seconds)

    Lower values will improve responsiveness - and increase the number of queries.

    Note that there may be multiple queues to poll, and each poll operation may take
    some #milliseconds. This number is not a simple `sleep()`; rather, it is a target.
    After doing a round of polling, we will sleep as long as necessary in
    order to meet the $pollInterval.

* __CLI Option__: `-d pollInterval=X`
* __Environment Variable__: _n/a_
* __File Field__: `pollInterval`
* __Class Property__: `$pollInterval`

### pollQuery

* __Description__: `coworker` must determine which queues to monitor.

    By default, it looks for queues which meet these two criteria:

    * `['status', '=', 'active']`
    * `['agent', 'CONTAINS', 'server']` (v5.68+) or `['runner', 'IS NOT EMPTY']` (v5.47-5.67)

    This option is an array-tree that will be passed to `Queue.get` (APIv4).

* __CLI Option__: _n/a_
* __Environment Variable__: _n/a_
* __File Field__: `pollQuery`
* __Class Property__: `$pollQuery`

## Options: Logging

### logFile

* __Description__: Store logs in a file
* __CLI Option__: `--log=X`
* __Environment Variable__: _n/a_
* __File Field__: `logFile`
* __Class Property__: `$logFile`

### logFormat

* __Description__: One of: `text|json`
* __CLI Option__: `-d logFormat=X`
* __Environment Variable__: _n/a_
* __File Field__: `logFormat`
* __Class Property__: `$logFormat`

### logInternalQueue

* __Description__: Should we enable logging for the internal-queue mechanism?

    After claiming a task, it is momentarily placed on an internal-queue while we find/setup resources for executing the
    task. By default, we exclude details about this from the log. However, you may re-enable it if you are specifically
    debugging issues coworker's task management.

* __CLI Option__: `-d logInternalQueue=1`
* __Environment Variable__: _n/a_
* __File Field__: `logInternalQueue`
* __Class Property__: `$logInternalQueue`

### logPolling

* __Description__: Should we enable polling-related debug info?

    The polling process sends a very large number of requests to the control-channel, and most of these don't result in
    anything interesting. By default, we exclude details about this from the log. However, you may re-enable it if you are
    specifically debugging issues with the polling mechanism.

* __CLI Option__: `-d logPolling=1`
* __Environment Variable__: _n/a_
* __File Field__: `logPolling`
* __Class Property__: `$logPolling`

### logLevel

* __Description__: Level of information to write to log file.

    One of: `debug|info|notice|warning|error|critical|alert|emergency`

* __CLI Option__: `-v` or `-vv`
* __Environment Variable__: _n/a_
* __File Field__: `logLevel`
* __Class Property__: `$logLevel`

## Options: Worker management

Coworker maintains a pool of reusable worker processes. These options determine when workers are started and stopped.

### workerCleanupCount

* __Description__: When we hit the maximum number of workers, we may need remove some idle processes. How many should we try to remove?
* __CLI Option__: `-d workerCleanupCount=X`
* __Environment Variable__: `COWORKER_GC_WORKERS`
* __File Field__: `workerCleanupCount`
* __Class Property__: `$workerCleanupCount`

### workerCount

* __Description__: Maximum number of workers that may be running at the same time.
* __CLI Option__: `-d workerCount=X`
* __Environment Variable__: `COWORKER_COUNT`
* __File Field__: `workerCount`
* __Class Property__: `$workerCount	`

### workerRequests

* __Description__: Maximum number of tasks to assign a single worker.

    After reaching this limit, no more tasks will be given to the worker.
* __CLI Option__: `-d workerRequests=X`
* __Environment Variable__: `COWORKER_REQUESTS`
* __File Field__: `workerRequests`
* __Class Property__: `$workerRequests`

### workerDuration

* __Description__: Maximum amount of time (seconds) for which a single worker should execute.

    After reaching this limit, no more tasks will be given to the worker.

* __CLI Option__: `-d workerDuration=X`
* __Environment Variable__: `COWORKER_DURATION`
* __File Field__: `workerDuration`
* __Class Property__: `$workerDuration`

### workerTimeout

* __Description__: If the worker is idle for $X seconds, then shut it down.
* __CLI Option__: `-d workerTimeout=X`
* __Environment Variable__: `COWORKER_TIMEOUT`
* __File Field__: `workerTimeout`
* __Class Property__: `$workerTimeout`

## Options: Generic

### agentDuration

* __Description__: Maximum amount of time (seconds) for which the overall system should run (inclusive of any/all workers).

    After reaching this limit, no more workers will be started, and no more tasks will be executed.

    (This option is intended to put a boundary when running E2E tests on coworker. It should not be needed in regular usage.)

* __CLI Option__: `-d agentDuration=X`
* __Environment Variable__: `COWORKER_AGENT_DURATION`
* __File Field__: `agentDuration`
* __Class Property__: `$agentDuration`
