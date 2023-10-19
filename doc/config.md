# coworker: Configuration

The `Configuration` class defines a series of options which may be specified using a configuration-file,
environment-variable, and/or CLI options.

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

If the same value is specified multiple ways, then the value will be chosen based on priority:

* Command line option (*highest priority*)
* Environment variable
* Configuration file
* Class default (*lowest priority*)
