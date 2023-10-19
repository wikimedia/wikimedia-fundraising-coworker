# coworker: Configuration

The coworker `Configuration` describes all options for fine-tuning coworkers behavior. It governs how long
CiviCRM processes live and how much work they can do. Configuration options can be set in the follows ways:

* Command line option (*highest priority*)
* Environment variable
* Configuration file (YAML/JSON)
* Class default (*lowest priority*)

## File format

Create a YAML file such as `/etc/coworker.yaml`:

```yaml
maxConcurrentWorkers: 5
maxWorkerDuration: 120
maxWorkerRequests: 10
maxWorkerIdle: 15
```

To use this file, pass the `-c` option:

```bash
coworker run -c /etc/coworker.yaml
```

> __TIP__: At time of writing, coworker does not read any files by default. It will only read a file if instructed to do so.

## Options

### minimumCivicrmVersion

Only run if the local CiviCRM deployment meets this minimum requirement.

* __Class Property__: `$minimumCivicrmVersion`
* __File Field__: `minimumCivicrmVersion`
* __Environment Variable__: _n/a_
* __CLI Option__: `-d minimumCivicrmVersion=X`

### maxConcurrentWorkers

Maximum number of workers that may be running at the same time.

* __Class Property__: `$maxConcurrentWorkers`
* __File Field__: `maxConcurrentWorkers`
* __Environment Variable__: `COWORKER_MAX_WORKERS`
* __CLI Option__: `-d maxConcurrentWorkers=X`

### maxWorkerRequests

Maximum number of tasks to assign a single worker.

After reaching this limit, no more tasks will be given to the worker.

* __Class Property__: `$maxWorkerRequests`
* __File Field__: `maxWorkerRequests`
* __Environment Variable__: `COWORKER_WORKER_REQUESTS`
* __CLI Option__: `-d maxWorkerRequests=X`

### maxWorkerDuration

Maximum amount of time (seconds) for which a single worker should execute.

After reaching this limit, no more tasks will be given to the worker.

* __Class Property__: `$maxWorkerDuration`
* __File Field__: `maxWorkerDuration`
* __Environment Variable__: `COWORKER_WORKER_DURATION`
* __CLI Option__: `-d maxWorkerDuration=X`

### maxWorkerIdle

If the worker is idle for $X seconds, then shut it down.

* __Class Property__: `$maxWorkerIdle`
* __File Field__: `maxWorkerIdle`
* __Environment Variable__: `COWORKER_WORKER_IDLE`
* __CLI Option__: `-d maxWorkerIdle=X`

### maxTotalDuration

Maximum amount of time (seconds) for which the overall system should run (inclusive of any/all workers).

After reaching this limit, no more workers will be started, and no more tasks will be executed.

(This option is intended to put a boundary when running E2E tests on coworker. It should not be needed in regular usage.)

* __Class Property__: `$maxTotalDuration`
* __File Field__: `maxTotalDuration`
* __Environment Variable__: `COWORKER_MAX_DURATION`
* __CLI Option__: `-d maxTotalDuration=X`

### gcWorkers

Garbage collection count

Whenever we hit the maximum, we have to remove some old workers. How many should we try to remove?

* __Class Property__: `$gcWorkers`
* __File Field__: `gcWorkers`
* __Environment Variable__: `COWORKER_GC_WORKERS`
* __CLI Option__: `-d gcWorkers=X`

### pipeCommand

External command used to start the pipe.

* __Class Property__: `$pipeCommand`
* __File Field__: `pipeCommand`
* __Environment Variable__: _n/a_
* __CLI Option__: `--pipe=X`

### logFile

Store logs in a file

* __Class Property__: `$logFile`
* __File Field__: `logFile`
* __Environment Variable__: _n/a_
* __CLI Option__: `--log=X`

### logLevel

Level of information to write to log file.

One of: `debug|info|notice|warning|error|critical|alert|emergency`

* __Class Property__: `$logLevel`
* __File Field__: `logLevel`
* __Environment Variable__: _n/a_
* __CLI Option__: `-v` or `-vv`

### logFormat

One of: `text|json`

* __Class Property__: `$logFormat`
* __File Field__: `logFormat`
* __Environment Variable__: _n/a_
* __CLI Option__: `-d logFormat=X`
