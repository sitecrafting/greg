# Greg

A de-coupled calendar solution for WordPress and Timber

## Installation

TODO

## Actions & Filters

TODO

## Customization

Override template files from your theme by placing them in a directory called `greg`.

## Command Line Interface (CLI)

Greg comes with some custom WP-CLI tooling:

```sh
wp greg
```

(In the dev environment, prefix this with `lando` to run it inside Lando!)

## Development

Clone this repo and start the dev environment using Lando:

```sh
lando start
```

NOTE: you may need to flush permalinks manually (Settings > Permalinks) for sub-pages to work.

## Testing

```sh
lando unit # run unit tests
lando integration # run integration tests
lando test # run all tests
```
