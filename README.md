# coworker

> __This project is in an early/pre-release phase.  The README gives a
> general overview of scope, but it may be out-of-sync with the code and
> depend on a mix of merged+unmerged core patches.__

Civi `coworker` is a task runner for CiviCRM -- it allows CiviCRM to run tasks in the background.

## Benefits

There are other paradigms for background processing in CiviCRM. `coworker` is distinct in supporting all these features *simultaneously*:

* __Multitasking__: `coworker` supports parallel execution of multiples tasks from multiple queues.
* __Generic__: `coworker` can execute diverse tasks, as defined by CiviCRM extensions. The sysadmin is not required to manually setup bespoke/per-task runners.
* __Compatible__: `coworker` is compatible with several deployment topologies - such as dedicated hosts, cloud hosts, and local hosts.
  Coworker uses local processes, SSH connections, and (*todo*) HTTP(S) connections.
* __Performant__: `coworker` can execute tasks with minimal delay (seconds or milliseconds - rather than minutes or hours).
* __Progressive enhancement__: `coworker` is compatible with almost any CiviCRM deployment type, but it gets progressively better if the deployment's technology permits.
* __Resource limits__: `coworker` respects multiple resource limits, such as #workers, worker-lifetime, and #requests-per-worker.
* __Redundancy__: A single CiviCRM deployment can have multiple `coworker`s. If one goes offline, the others continue.

## Summary

The basic process is to [download `coworker`](doc/install.md) and start the command `coworker run`, e.g.

```bash
## Usage
coworker run [CONNECTION_OPTIONS]

## Examples
coworker run
coworker run --cwd=/var/www/example.com 
coworker run --pipe='drush @example.com ev "civicrm_initialize(); Civi::pipe();"'
coworker run --web='https://example.com/civicrm/queue?token=XXX'
```

By default, `coworker run` will attempt to auto-detect a local CiviCRM instance from the current folder (based on [cv bootstrap
process](https://github.com/civicrm/cv/#bootstrap)).  However, you may need to configure the [`CONNECTION_OPTIONS`](doc/connect.md) -- especially if
using a multisite CMS or connecting to a remote server.

If the connection succeeds, then `coworker` will monitor CiviCRM for new tasks and execute them.

> __TIP__: For CiviCRM developers, consider using `coworker debug` to run tasks with less parallel and more
> debug information.
>
> __TIP__: For CiviCRM administrators, consider adding a `systemd` unit to launch `coworker`.

## Documentation

* [Download / Install](doc/install.md)
* [Connecting to CiviCRM](doc/connect.md)
* [Configuring coworker](doc/config.md)
* [Development](doc/develop.md)
