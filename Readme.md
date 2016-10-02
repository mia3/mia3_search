# mia3_search

## installation

1. install mia3_search extension through "composer require mia3/mia3_search"
2. include mia3_search typoscript
3. insert search plugin to search page

## indexing

**manual indexing**

you can use the ```index:update`` command to manually start the indexing process

```
./typo3/cli_dispatch.phpsh extbase index:update
```

**periodic indexing**

you can use the scheduler to schedule a peridoc execution of the ```index:update``` command 

## configuration

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

