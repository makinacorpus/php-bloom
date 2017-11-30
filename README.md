# PHP Bloom filter

[![Build Status](https://travis-ci.org/makinacorpus/php-bloom.svg?branch=master)](https://travis-ci.org/makinacorpus/php-bloom)

This is a simple PHP Bloom filter implementation using Sherif Ramadan's
implementation.

Original code and a really great explaination can be found here
http://phpden.info/Bloom-filters-in-PHP

It is slightly modified to correct some coding standard issues, to achieve
a more flexible runtime configuration, and fixes a few performance issues.

## Usage

You must first choose a targetted maximum number of elements that your filter
will contain, and a false positive implementation, obviously the lesser are
those two numbers, the faster the implementation will be.

```php
// You may cache this value, and fetch it back, it's the whole goal of this
// API. Beware that the stored string might contain ``\0`` characters, ensure
// your storage API deals with those strings in safe way.
$value = null;

// Configure your Bloom filter, if you store the value, you should store the
// configuration along since selected hash algorithms and string size would
// change otherwise.
$probability = 0.0001
$maxSize = 10000;

$filter = new \MakinaCorpus\Bloom\BloomFilter();

// You may add as many elements as you wish, elements can be any type, really,
// if not scalar they will be serialized prior to being hashed.
$filter->set('some_string');
$filter->set(123456);
$filter->set(['some' => 'array']);
$filter->set(new \stdClass());

// And the whole goal of it:
if ($filter->check('some_value')) {
  do_something();
}

```

## Notes

Please carefully read the original author's blog post, since it explains
everything you need to know about Bloom filters: http://phpden.info/Bloom-filters-in-PHP

Please also use it wisely, the hashing algorithms are quite fast, but if you
do use it too much, it will impact negatively on your CPU usage.

There are numerous other competitive implementations, you may use whichever
seems the best for you, take a look around before choosing.
