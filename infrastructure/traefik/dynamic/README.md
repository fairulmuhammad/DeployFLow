# Dynamic Traefik configuration.

One file per environment, written by the deployment agent (M5) as
`<environment_id>.yml`. The agent writes a temporary file, fsyncs it, then
atomically renames it over the live file so Traefik never observes a partial
router.

This directory is bind-mounted read-only into the Traefik container. Keep it
empty until the deployment agent exists; any file dropped here becomes live
routing configuration immediately.
