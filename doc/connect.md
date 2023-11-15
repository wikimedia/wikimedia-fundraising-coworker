# coworker: Connecting to CiviCRM

The `coworker run` command connects to CiviCRM, requests pending tasks, and executes them. This requires a communication channel, and each communication
channel has different qualities.

| Communication Channel | Description | Compatibility | Latency | RAM/CPU Limits |
| -- | -- | -- | -- | -- |
| `pipe` (local) | Send bidirectional messages to a long-running CiviCRM process. | Servers with full sysadmin access | Low latency | Configurable |
| `pipe` (SSH/etc) | Send bidirectional messages to a long-running CiviCRM process. | Remote servers with SSH access (or similar) | Mixed (high-latency setup; low-latency message) | Configurable |
| `web` (*todo*) | Send HTTP requests to a remote CiviCRM web server. | All web servers | Medium-high latency | Assigned by web-server |

* __Compatibility__: Which CiviCRM deployments can use this type of connection?
* __Latency__: How quickly do tasks start when using this type of connection?
* __RAM/CPU Limits__: How are limits set for tasks?

This page discusses connection options.

## Pipe

In Unix-style systems, a *pipe* is a flexible and performant mechanism for exchanging data and commands. To allow quicker processing of background tasks,
Coworker may start a CiviCRM process and exchange data through a pipe. However, the setup process may require greater access and more steps.

* __Strengths__: Once a pipe is started, it can be used repeatedly - which enables faster polling and reduced latency. Piped processes may be
  allowed to use additional resources (RAM and CPU time) that are unavailable to web requests. Piped processes use a pooling strategy that
  is optimized for background work.
* __Weaknesses__: Setup requires higher level of sysadmin access (minimally, SSH access; ideally, `sudo` or `root` access).

There are several ways to start `coworker` with a CiviCRM pipe.  These may use [cv](https://github.com/civicrm/cv), [drush](https://drush.org), or
[wp-cli](https://wp-cli.org/), as in:

```bash
## Start with coworker's built-in pipe adapter
cd /var/www/example.com/web
coworker run

## Start with cv
cd /var/www/example.com/web
coworker run --pipe='cv pipe'

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
< {"Civi::pipe":{"v":"5.47.alpha1","t":"trusted","l":["login"]}}
> {"jsonrpc":"2.0","method":"echo","params":["hello world"],"id":null}
< {"jsonrpc":"2.0","result":["hello world"],"id":null}
```

(To close the session, press `Ctrl-D` to disconnect.)

### Pipe negotiation flags

Pipe connections may use negotiation flags during the initial setup. You generally should not need to customize them.
But just in case, here are some examples of setting some flags (`t` `v` `j`):

```bash
coworker run --pipe='MINIPIPE tvj'
coworker run --pipe='cv pipe tvj'
coworker run --pipe='drush ev "civicrm_initialize(); Civi::pipe(\'tvj\');"'
coworker run --pipe='wp eval "civicrm_initialize(); Civi::pipe(\'tvj\');"'
```

## HTTP (*todo*)

To run tasks remotely using HTTP:

```
coworker run --web='https://example.com/civicrm/queue?token=XXX'
```

(*FIXME: discuss credentials management, authx, etc; maybe rework as pure JWT?*)

This method comes with some trade-offs:

* __Strengths__: Works with any web server architecture. If you have multiple web servers, requests will be automatically distributed among them.
* __Weaknesses__: To avoid overloading the web server, polling intervals are fairly long. Every request requires a full bootstrap. The
  resource limits (RAM and CPU time) for web servers are often a bit tight.

## Hybrid (Pipe+HTTP) (*todo*)

In this example, we monitor for new tasks with a long-running SSH pipe, and then
execute specific tasks with medium-latency HTTPS requests.

```bash
coworker run --channel=pipe,web \
  --pipe='ssh webuser@backend.example.com cv ev --cwd=/var/www/example.com/web ev "Civi::pipe();"' \
  --web='https://user:pass@example.com/civicrm/queue'
```
