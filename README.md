# Greg

> I mean, do you really want to deal with The Events Calendar?
>
> -- Pope Gregory XIII

[![Build Status](https://api.travis-ci.org/sitecrafting/greg.svg?branch=main)](https://travis-ci.org/github/sitecrafting/greg)

A de-coupled calendar solution for WordPress and Timber. Leverage [RRULE](https://github.com/rlanvin/php-rrule) to store only the recurrence rules for your recurring events. Supports one-time and recurring events.

## Contents

* [Rationale](#rationale)
* [Requirements](#requirements)
* [Installation](#installation)
* [Quick Start](#quick-start)
* [Basic Usage](#basic-usage)
* [Actions & Filters](#actions--filters)
* [Command Line Interface](#command-line-interface-cli)
* [Development](#development)
* [Testing](#testing)
* [Roadmap](#roadmap)

## Rationale

Let's get one thing out of the way: Greg is **not** a drop-in replacement for The Events Calendar. Greg is designed to be much more flexible code-wise, with the trade-off of being a little less "plug-n-play" from an end-user's perspective. Some key differences:

* Rather than the plugin rendering its own WP Admin user interface (UI), you build your own backend fields (using ACF or something similar) and tell Greg where to find that data (see [Basic Usage](#basic-usage) for details).
* The Events Calendar is completely standalone (Note: The Events Calendar PRO is a paid add-on to The Events Calendar), whereas Greg relies on [Timber](https://timber.github.io/docs/) and, by extension, [Twig](https://twig.symfony.com/).
* The Events Calendar (and most other WP event management plugins out there) store one post **per event recurrence**. This causes all kinds of problems, including slow database queries, confusing and burdensome data management issues, and **tons** of incidental complexity. Greg cuts away all of that.

### Use Greg if:

* You want to manage recurring events in a simple, reasonably performant way
* You are OK building out your own backend UI for Events using ACF or similar (a default backend UI is on the [Roadmap](#roadmap) but is not implemented yet)
* You are already running Timber/Twig and want to integrate your frontend Event code with that

### Do NOT use Greg if:

* You want a plug-n-play event management system that renders your event calendar/listing/detail pages for you

* You want a WP Admin page with tons of settings you can control without code updates

* You want a standalone solution (not reliant on Timber)

## Requirements

  * PHP >= 7.4
  * WordPress Core >= 5.5.1
  * [Timber >= 2.0](https://timber.github.io/docs/v2/)

## Installation

### Manual Installation

Go to the GitHub [releases page](https://github.com/sitecrafting/greg/releases/) and download the .zip archive of the latest release. Make sure you download the release archive, **not** the source code archive. For example, if the latest release is called `v0.x.x`, click the download link that says **greg-v0.x.x.zip**. (You can also use the `tar.gz` archive if you want - they are the same code.)

Once downloaded and unzipped, place the extracted directory in `wp-content/plugins`. Activate the plugin from the WP Admin as you normally would.

### Composer (advanced)

Add to composer.json:

```sh
composer require sitecrafting/greg
```

Greg is PSR-4 compliant, so assuming you've required your `vendor/autoload.php` as you normally would, you can `use Greg\*` classes from anywhere.

## Quick Start

```php
/* archive-greg_event.php */

use Timber\Timber;

$data = Timber::context();

$data['events'] = Greg\get_events();

Timber::render('archive-greg_event.twig', $data);
```

```twig
{# views/archive-greg_event.twig #}
{% extends 'layouts/my-main-layout.twig' %}

{% block main_content %}
  <main class="event-listing">
    <h1>Events </h1>

    {% for event in events %}
      <article>
        <h2><a href="{{ event.link }}">{{ event.title }}</a></h2>
        {# October 31, 11:10 am - 1:30 pm #}
        <h3>{{ event.range('F j, g:ia', 'g:ia') }}</h2>
        <section>{{ event.content }}</section>
      </article>
    {% endfor %}

    <div class="pagination">
      <a href="?event_month={{ greg_prev_month() }}">{{ greg_prev_month('F') }} Events</a>
      <a href="?event_month={{ greg_next_month() }}">{{ greg_next_month('F') }} Events</a>
    </div>

  </main>
{% endblock %}
```

**TODO:** add quick start guide for category archives and event details

## Basic Usage

Greg is built to have no opinion about what your site looks like, and is solely concerned with making your event data easier to work with. You store RRULE data for each event describing:

* start/end dates
* recurrence frequency
* recurrence length (i.e. recurs **until**)
* any exceptions to the recurrence rules (e.g. every Friday **except** 2020-11-13)

The only assumption about your data is that it's stored in post meta fields. Using a simple API, you tell Greg how to find this data. Later, at query time, you ask Greg for certain events (matching time-based/category criteria, and any other criteria you like) and it gives you back **all** recurrences of **all** matching events it finds.

### Conceptual example

Say you have the following events:

* **Hip-hop Dance Nights**: starts Jan 1 and recurs **weekly** at 5pm until Jan 22
* **Karate Tournament**: starts Jan 3 and recurs **daily** at 10am until Jan 6
* **Troll 2 Screening**: **one night only** on Jan 4 at 9pm

When you query for events in January, Greg gives you a `Greg\Event` object back **for each recurrence**:

* **Hip-hop Dance** on Jan 1
* **Karate Tournament** on Jan 3
* **Karate Tournament** on Jan 4
* **Troll 2 Screening** (after Karate Tournament, since it's later in the day)
* **Karate Tournament** on Jan 5
* **Karate Tournament** on Jan 6
* **Hip-hop Dance** on Jan 8
* **Hip-hop Dance** on Jan 15
* **Hip-hop Dance** on Jan 22

If you want to, you can also tell Greg to give you just the three Events, in case you want to display info about each series as a whole.

### API example

```php
add_filter('greg/meta_keys', function() : array {
  return [
    'start'                  => 'my_start_date_key',
    'end'                    => 'my_end_date_key',
    'frequency'              => 'my_frequency_key',
    'until'                  => 'my_until_key',
    'exceptions'             => 'my_exceptions_key',
    'recurrence_description' => 'my_recurrence_description',
  ];
});
```

Greg uses this info to figure out how to do time-based queries for events, and also how to fetch recurrence rules a given event post.

Now we're ready to actually fetch our posts. To do that we call `Greg\get_events()`, which is just like `Timber::get_posts()`, but with some syntax sugar for nice, concise queries:

```php
/* archive-greg_event.php */

use Timber\Timber;

$event = Greg\get_events();

$context = Timber::context();
$context['posts'] = $events;

Timber::render('archive-greg_event.twig', $context);
```

You can also specify month and/or event category:

```php
$events = Greg\get_events([
  'event_month'    => '2020-11',
  'event_category' => 'cool',
  // OR by term_id:
  // 'event_category' => 123,
]);

// Assuming the greg/meta_keys hook in the example above,
// this query expands to:
$events = Timber::get_posts([
  'tax_query'  => [
    [
      'taxonomy' => 'greg_event_category',
      'terms'    => ['cool'],
      'field'    => 'slug',

      // OR if you passed an int:
      // 'terms' => [123],
      // 'field' => 'term_id',

      // OR if you passed an array of strings:
      // 'terms' => ['array', 'of', 'slugs'],
      // 'field' => 'slug',

      // OR an array of ints:
      // 'terms' => [123, 456],
      // 'field' => 'term_id',
    ],
  ],
  // NOTE: it uses meta_query and not date_query,
  // since this is completetly different from post_date
  'meta_query'   => [
    'relation'   => 'AND',
    [
      'key'      => 'my_start_date_key',
      'value'    => '2020-11-01',
      'compare'  => '>=',
      'type'     => 'DATETIME',
    ],
    // ... additional constraints by my_end_date_key here ...
  ],
]);
```

This returns an array of `Greg\Event` objects. These are wrappers around `Timber\Post` objects that acts like a Post object but has some extra data/methods for things like date ranges. Each `Event` represents a single recurrence of an event, so a Post can expand to one or more Events.

Passing a string for `event_category` indicates it's a term slug; int means it's a `term_id`. You can also pass an array of strings or ints to query for events by any (inclusive) corresponding slugs/`term_id`s, respectively.

Instead of `month`, you can also specify a date range via separate `start` and `end` params:

```php
Greg\get_events([
  // Just picking some dates at random here, totally not preoccupied with anything...
  'start'  => '2020-11-03',
  'end'    => '2021-01-20',

  // Other params...
]);
```

The time-based filters `month` and `start`/`end` work independently of category, as you'd expect.

### Querying for event series only

By default, Greg will parse out any recurrence rules it finds and expand all recurring events out into their respective individual recurrences, finally sorting all recurrences by start date. But that may not be what you want all the time. You can tell Greg you only want the series, not the individual recurrences, using the special `expand_recurrences` param set to `false`:

```php
Greg\get_events([
  'expand_recurrences' => false
]);
```

The `expand_recurrences` param composes with any other valid Greg/`WP_Query` params: that is, it doesn't actually affect the database query logic in any way, it just tells Greg to skip the step of expanding recurrences.

### Additional params
Any parameters not explicitly mentioned above get passed through as-is, so you can for example use the WordPress native `offset` param to exclude the first five events from your results:

```php
Greg\get_events([
  'event_month'  => '2020-11', // Greg expands this into a meta_query as usual.
  'offset'       => 5,         // This gets passed straight through to WordPress.
]);
```

If you specify any custom `meta_query` or `tax_query` params, Greg will merge them in with its own time-based/category sub-queries.

### Performance

Greg is currently not as optimized as it could be. Specifically, to fetch the actual meta data it currently does a few database lookups for each recurring event in a result set. This is pretty typical of WordPress code, where you don't usually fetch meta data until after you've already queried for your post(s). But a faster way would be to map between a post ID and a recurrence ruleset in a *single* query result, so that for a collection of events of any size, we only need one round trip to the database. This would require some advanced logic to compile custom SQL clauses at query time, and may become available in a future version.

### Templates & Views

Greg takes full advantage of Timber's use of the [Twig template engine](https://twig.symfony.com/). While Greg's frontend code is completely optional, the basic views provided are useful out of the box and are completely customizable.

#### In PHP: `Greg\render()`

You can render a Twig view from PHP with the `Greg\render()` function:

```php
Greg\render('event-categories.twig');
```

Note that unlike `Timber::render()`, a static method on the Timber class, this is a plain PHP function in the `Greg` namespace, hence the backslash notation `Greg\render()` instead of the double-colon.

Like `Timber::render()`, it takes an optional array of data to pass to the Twig view:

```php
Greg\render('event-categories.twig', [
  'extra' => 'stuff',
]);
```

You don't need to do this unless you're overriding Greg's views from your theme  (and in fact passing extra data to the default views has no effect, since they don't care about the extra data passed to them). More on that below.

There is also a `Greg\compile()` method which returns the compiled string instead of just echoing it, in a way exactly analogous to the `Timber::compile()` method.

#### In Twig: `greg_render()`

To render a view straight from Twig code, use the `greg_render()` Twig function:

```twig
<aside class="event-cats-container">
  {{ greg_render('event-categories.twig') }}
</aside>
```

As with the `Greg\render()` PHP function, you can pass extra data:

```twig
<aside class="event-cats-container">
  {{ greg_render('event-categories.twig'), { extra: 'stuff' } }}
</aside>
```

### Available views

Any of the following views can be rendered directly (e.g. passed to `greg_render()`):

#### `events-list.twig`

List all events.

##### Params:

* `params`: query params passed to `Greg\get_events()`. Default: `[]`
* `events`: all events

##### Example

```twig
{{ greg_render('events-list.twig', { params: { event_month: '2020-11' } }) }}
```

#### `event-categories-list.twig`

List all Event Categories.

##### Params

* `term`: `Timber\Term` object for overriding the "current" term
* `terms`: array of `Timber\Term` objects to override the listed categories

##### Example

```twig
{{ greg_render('event-categories-list.twig', { term: { my_term } }) }}
```

#### `event-details.twig`

Display Event Details.

##### Params

* `term`: `Timber\Term` object for overriding the "current" term
* `terms`: array of `Timber\Term` objects to override the listed categories

##### Example

```twig
{{ greg_render('event-categories-list.twig', { term: { my_term } }) }}
```



### Overriding Greg's views

A view can be overridden from your theme simply by placing it at a specific path relative to your theme route. For example, by placing a file at `./views/greg/event-categories.twig`, you tell Greg to render your theme's `event-categories` view instead of Greg's built-in one.

Greg transparently passes any extra data you pass to `greg_render`/`Greg\render()`:

```twig
{# views/greg.twig #}
<div class="event-cats">
  {# render each event cat here... #}

  {# extra data passed to greg_render() #}
  <p>{{ extra }}</p>
</div>
```

#### Providing and overriding view data

Use the `greg/render/$view_name.twig` filter to override data that gets passed to **any** Greg view, any time it's rendered:

```php
add_filter('greg/render/event-categories.twig', function(array $data) : array {
  // add some extra stuff any time event-categories.twig gets rendered
  return array_merge($data, [
    'extra' => 'stuff',
  ]);
});
```

Again, since the default views don't care about any extra data passed to them, you don't need to worry about this unless you are overriding Greg's views from your theme. Because you can always pass a data array directly to `greg_render()`/`Greg\render()`, this is an advanced use-case that you really only need if you want to customize view data **across all usage of a given view.**

## Actions & Filters

### Event query params

To make customizations across all Greg queries, you can hook into the `greg/query/params` filter. For example, say you just want a simple event series manager, and never need to list individual recurrences:

```php
add_filter('greg/query/params', function(array $params) : array {
  $params['expand_recurrences'] = false;
  return $params;
});
```

The hook runs inside `Greg\get_events()` **before** the params are expanded into meta/taxonomy queries as described above, which is how it can honor special keys like `expand_recurrences`.

### Query params: A more advanced example

Say you have a custom `location` post type associated with each event by the meta_key `location_id`, and on single location pages you want to query only for events at that location. (In this contrived scenario, there's no reason you couldn't simply run the query directly from your template. But humor me for a second and just imagine there's some reason you can't. Okay? Cool.) You can hook into Greg's query logic to accomplish this:

```php
add_filter('greg/query/params', function(array $params) : array {
  if (is_single()) {
    $params['meta_query'] = [
      [
        'key'   => 'location_id',
        'value' => get_the_ID(),
      ],
    ];
  }

  return $params;
});
```

Since Greg simply uses `Timber::get_posts()` under the hood, the array returned from this hook can be any valid arguments to [`WP_Query::__construct()`](https://developer.wordpress.org/reference/classes/wp_query/__construct/).

## Command Line Interface (CLI)

Greg comes with some custom WP-CLI tooling:

```sh
wp greg # describe sub-commands
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

## Roadmap

- Performance optimizations (see [**Performance**](#performance), above)
- REST endpoints for listing/singular events
- Default (but still optional) admin GUI using the configured meta keys
- Shortcodes for listing Events, listing Event Categories, Event Details, etc.
