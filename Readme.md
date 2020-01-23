# mia3_search

## Installation

1. install mia3_search extension through "composer require mia3/mia3_search"
2. include mia3_search typoscript
3. make sure you have a domain entry on every root site you wish to index
4. insert search plugin to search page
5. you're done, start by indexing using the command controller or setup the scheduler.

## Indexing

**manual indexing**

you can use the ```index:update`` command to manually start the indexing process

```
./vendor/bin/typo3 mia3search:index
```

**periodic indexing**

you can use the scheduler to schedule a periodic execution of the ```index:update``` command 

## Configuration

### Adapters

mia3_search is based on [mia3/saku](https://github.com/mia3/saku) a php package to provide a common interface for multiple search backends.

**Typo3MySQLAdapter (default)**

```
plugin.tx_mia3search_search {
   settings {
      adapter = \MIA3\Mia3Search\Adapter\Typo3MySQLAdapter
   }
}
```

**ElasticSearchAdapter**

```
plugin.tx_mia3search_search {
   settings {
      adapter = \MIA3\Saku\Adapter\ElasticSearchAdapter
      hosts = http://localhost:30080/
      index = some_index_name
   }
}
```

### excluding content from the index

if you need to exclude something from the index you can simple add a class to the containing html element in your template. anything tagged with the class ```.mia3-search-unindexed``` will be removed from the content before being indexed. Instead of adding that class to your html you can also specify additional css selectors in the ```$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist']``` array in your ```ext_localconf.php```.

### Content processing

the content is already heavily processed before being put into the index:

- **cssFilter**: filter out any html that is matched by the indexingBlacklist selectors
- **ensureWhitespaceBetweenTags**: make sure, there is at least one whitespace character between words, even, if they'd be bunched together after stripping the html tags
- **scriptTags**: remove all inline script tags, including their content
- **styleTags**: remove all inline style tags, including their content
- **stripTags**: strip all html tags
- **lineBreaks**: remove all line breaks

every content processor can be disabled or changed be unsetting or editing it in the ```$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters']``` array.
and you can add any additional processors you need as well.
