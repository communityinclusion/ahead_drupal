# Search API Saved Searches

This module offers visitors the ability to save searches executed with the
[Search API module] and be notified of new results. Notifications are done
via mails with token-replacement, their frequency can be configured both by
admins and/or visitors and saved searches can also be created without first
executing the search.

[Search API module]: https://www.drupal.org/project/search_api

[Facets], Views or other filters set for the search will also be included in a
saved search, thus reflecting exactly the same search results as displayed.

[Facets]: https://www.drupal.org/project/facets


## Requirements

- [Search API module]


## Installation

1. Enable the module as usual.
2. Go to `/admin/config/search/search-api-saved-searches` to configure the
   module.
3. Go to `/admin/structure/block` and place the “Save search” block.


## Troubleshooting & FAQ

For bug reports, feature suggestions and support requests, as well as following
the latest developments, visit the [module’s issue queue][issue queue].

[issue queue]: https://www.drupal.org/project/issues/search_api_saved_searches


### Known problems

Currently, the “Save search” block is not consistently displayed when a search 
view uses caching. Disable caching for search views if you want users to be able
to save them. See [this issue][issue-3061088] for details.

[issue-3061088]: https://www.drupal.org/project/search_api_saved_searches/issues/3061088


## Maintainers

- [Thomas Seidl (drunken monkey)](https://www.drupal.org/u/drunken-monkey)
